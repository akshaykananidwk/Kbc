<?php
declare(strict_types=1);

/**
 * Second half of the verification suite: the game engine, the two screens,
 * display security, transactions, backups and the updater.
 *
 * Included by tests/verify.php - not meant to be run on its own.
 *
 * @var Suite $suite
 * @var Http $http
 * @var Http $op
 * @var App\Core\Database $db
 * @var string $baseUrl
 * @var int $participantId
 */

use App\Services\BackupService;
use App\Services\CacheService;
use App\Services\GameService;
use App\Services\MigrationService;
use App\Services\SettingsService;
use App\Services\UpdateService;

// A clean slate so the assertions below are deterministic.
$db->run('DELETE FROM game_events');
$db->run('DELETE FROM game_answers');
$db->run('DELETE FROM game_lifelines');
$db->run('DELETE FROM game_questions');
$db->run('DELETE FROM games');
$db->run("UPDATE gifts SET quantity_used = 0, status = 'active'");
$db->run("UPDATE questions SET status = 'active' WHERE status = 'inactive' AND times_used = 0");

/** POST helper: fetches a fresh CSRF token, then posts JSON. */
$api = static function (string $path, array $payload = []) use ($http): array {
    $http->get('/admin');
    return $http->json($path, $payload, $http->token());
};

/** GET helper for the read-only operator endpoints. */
$apiGet = static fn (string $path): array => $http->getJson($path);

/** The public display payload, exactly as the TV browser receives it. */
$display = static function () use ($baseUrl): array {
    $raw = @file_get_contents($baseUrl . '/api/display/snapshot');
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) && isset($decoded['data']) ? $decoded['data'] : [];
};

/**
 * Distinguishes "the key is absent" from "the key is present and null" -
 * which is exactly the distinction the display-security checks depend on.
 */
$peek = static function (array $data, string $dotted): array {
    $node = $data;
    foreach (explode('.', $dotted) as $segment) {
        if (!is_array($node) || !array_key_exists($segment, $node)) {
            return ['exists' => false, 'value' => null];
        }
        $node = $node[$segment];
    }
    return ['exists' => true, 'value' => $node];
};

// ---------------------------------------------------------------------------
// 6. Game engine
// ---------------------------------------------------------------------------
$suite->module('6. Game engine');

$result = $api('/api/game/create', ['participant_id' => $participantId]);
$suite->check('game created', ($result['success'] ?? false) === true, (string) ($result['message'] ?? ''));
$gameId = (int) ($result['data']['game_id'] ?? 0);
$suite->equals('initial state', (string) ($result['data']['state'] ?? ''), GameService::STATE_INTRO);

$result = $api('/api/game/start');
$suite->equals('game starts at level 1', (int) ($result['data']['level'] ?? 0), 1);
$suite->equals('state after start', (string) ($result['data']['state'] ?? ''), GameService::STATE_QUESTION);
$suite->check('a question is on air', ($result['data']['question'] ?? null) !== null, (string) mb_substr((string) ($result['data']['question']['text'] ?? ''), 0, 40));

$totalMs = (int) ($result['data']['timer']['total_ms'] ?? 0);
$suite->check('timer is loaded but not running', $totalMs > 0 && ($result['data']['timer']['running'] ?? true) === false, $totalMs . 'ms');

$result = $api('/api/game/timer/start');
$suite->check('timer starts', ($result['data']['timer']['running'] ?? false) === true, 'running');
usleep(600000);
$snapshot = $apiGet('/api/game/state');
$remainingRunning = (int) ($snapshot['data']['timer']['remaining_ms'] ?? 0);
$suite->check('timer counts down in real time',
    $remainingRunning > 0 && $remainingRunning < $totalMs && ($totalMs - $remainingRunning) >= 400,
    sprintf('%dms left of %dms after 600ms', $remainingRunning, $totalMs));

$result = $api('/api/game/timer/pause');
$pausedAt = (int) ($result['data']['timer']['remaining_ms'] ?? 0);
$suite->equals('timer pauses', (string) ($result['data']['state'] ?? ''), GameService::STATE_TIMER_PAUSED);
usleep(500000);
$snapshot = $apiGet('/api/game/state');
$suite->check('paused timer does not drift', abs((int) ($snapshot['data']['timer']['remaining_ms'] ?? 0) - $pausedAt) < 50, 'held at ' . $pausedAt . 'ms');

$result = $api('/api/game/timer/resume');
$suite->check('timer resumes', ($result['data']['timer']['running'] ?? false) === true, 'running');

$result = $api('/api/game/timer/reset');
$suite->equals('timer resets to full', (int) ($result['data']['timer']['remaining_ms'] ?? 0), $totalMs);

// Lifelines
$result = $api('/api/lifelines/use', ['code' => 'fifty_fifty']);
$lifelines = [];
foreach (($result['data']['lifelines'] ?? []) as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$removed = $lifelines['fifty_fifty']['result']['removed'] ?? [];
$remaining = $lifelines['fifty_fifty']['result']['remaining'] ?? [];
$correct = (string) ($result['data']['private']['correct_option'] ?? '');
$suite->equals('50:50 removes two options', count($removed), 2);
$suite->check('50:50 keeps the correct answer', in_array($correct, $remaining, true), implode(',', $remaining) . ' kept');

$result = $api('/api/lifelines/use', ['code' => 'fifty_fifty']);
$suite->check('a lifeline cannot be used twice', ($result['success'] ?? true) === false, (string) ($result['message'] ?? ''));

$result = $api('/api/lifelines/use', ['code' => 'audience_poll']);
foreach (($result['data']['lifelines'] ?? []) as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$percentages = $lifelines['audience_poll']['result']['percentages'] ?? [];
$suite->equals('audience poll totals 100%', array_sum($percentages), 100);
$removedShare = 0;
foreach ($removed as $key) { $removedShare += (int) ($percentages[$key] ?? 0); }
$suite->equals('poll gives 0% to options 50:50 removed', $removedShare, 0);

$result = $api('/api/lifelines/use', ['code' => 'expert_advice']);
foreach (($result['data']['lifelines'] ?? []) as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$suggested = (string) ($lifelines['expert_advice']['result']['suggested_option'] ?? '');
$suite->check('expert suggests a surviving option', in_array($suggested, $remaining, true), 'suggested ' . $suggested);

// ---------------------------------------------------------------------------
// 7. Display screen security
// ---------------------------------------------------------------------------
$suite->module('7. Display screen security (the critical requirement)');
$displayState = $display();
$raw = json_encode($displayState, JSON_UNESCAPED_UNICODE) ?: '';
$correctField = $peek($displayState, 'answer.correct');
$suite->check('correct answer is null before the reveal',
    $correctField['exists'] && $correctField['value'] === null,
    $correctField['exists'] ? 'present and null' : 'key absent entirely');
$explanationField = $peek($displayState, 'question.explanation');
$suite->check('explanation is null before the reveal',
    $explanationField['exists'] && $explanationField['value'] === null,
    $explanationField['exists'] ? 'present and null' : 'key absent entirely');
$suite->check('no "private" section in the display payload', !array_key_exists('private', $displayState));
$suite->check('the string "correct_option" appears nowhere', !str_contains($raw, 'correct_option'));
$suite->check('no password or token data anywhere',
    !str_contains($raw, 'password') && !str_contains($raw, 'github') && !str_contains($raw, 'DB_'));
$suite->check('options removed by 50:50 are null, not hidden client-side',
    $displayState['question']['options'][$removed[0]] === null, $removed[0] . ' = null');
$operatorState = $apiGet('/api/game/state');
$suite->check('the operator DOES receive the correct answer at the same moment',
    (string) ($operatorState['data']['private']['correct_option'] ?? '') === $correct, 'operator sees ' . $correct);

// ---------------------------------------------------------------------------
// 8. Answer flow
// ---------------------------------------------------------------------------
$suite->module('8. Answer flow');
$wrongOption = '';
foreach ($remaining as $option) { if ($option !== $correct) { $wrongOption = $option; break; } }

$result = $api('/api/game/select', ['option' => $removed[0]]);
$suite->check('an option removed by 50:50 cannot be selected', ($result['success'] ?? true) === false, (string) ($result['message'] ?? ''));

$result = $api('/api/game/select', ['option' => $wrongOption]);
$suite->equals('option selected', (string) ($result['data']['answer']['selected'] ?? ''), $wrongOption);
$result = $api('/api/game/select', ['option' => $correct]);
$suite->equals('selection can be changed before locking', (string) ($result['data']['answer']['selected'] ?? ''), $correct);

$result = $api('/api/game/reveal');
$suite->check('reveal is refused before the answer is locked', ($result['success'] ?? true) === false, (string) ($result['message'] ?? ''));

$result = $api('/api/game/lock');
$suite->check('answer locks', ($result['data']['answer']['locked'] ?? false) === true, 'locked');
$suite->check('locking stops the timer', ($result['data']['timer']['running'] ?? true) === false, 'timer stopped');

$result = $api('/api/game/select', ['option' => $wrongOption]);
$suite->check('a locked answer cannot be changed', ($result['success'] ?? true) === false, (string) ($result['message'] ?? ''));

$displayState = $display();
$lockedField = $peek($displayState, 'answer.correct');
$suite->check('correct answer STILL hidden after locking',
    $lockedField['exists'] && $lockedField['value'] === null, 'still null');
$suite->check('display shows the answer as locked', ($displayState['answer']['locked'] ?? false) === true);

$auditBefore = (int) $db->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'game.answer_override'");
$result = $api('/api/game/unlock', ['reason' => 'verification suite override']);
$suite->check('operator override unlocks the answer', ($result['data']['answer']['locked'] ?? true) === false, 'unlocked');
$suite->equals('the override is written to the audit log',
    (int) $db->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'game.answer_override'"), $auditBefore + 1);

$api('/api/game/lock');
$result = $api('/api/game/reveal');
$suite->equals('correct answer gives state CORRECT', (string) ($result['data']['state'] ?? ''), GameService::STATE_CORRECT);
$suite->equals('prize awarded for level 1', (float) ($result['data']['prize']['won_so_far'] ?? 0), (float) $db->scalar('SELECT amount FROM prize_levels WHERE level_no = 1'));

$displayState = $display();
$suite->equals('correct answer IS released after the reveal', (string) ($displayState['answer']['correct'] ?? ''), $correct);
$suite->check('explanation released after the reveal', ($displayState['question']['explanation'] ?? null) !== null);

// ---------------------------------------------------------------------------
// 9. Two screen synchronisation
// ---------------------------------------------------------------------------
$suite->module('9. Two screen synchronisation');
$versionBefore = (int) ($display()['state_version'] ?? 0);
$api('/api/game/next');
$versionAfter = (int) ($display()['state_version'] ?? 0);
$suite->check('state_version advances on every operator action', $versionAfter > $versionBefore, $versionBefore . ' -> ' . $versionAfter);

$displayState = $display();
$operatorState = $apiGet('/api/game/state');
$operatorData = $operatorState['data'] ?? [];
$suite->check('operator state endpoint responded', ($operatorState['success'] ?? false) === true, (string) ($operatorState['message'] ?? ''));
$suite->equals('both screens agree on the level', (int) ($displayState['level'] ?? -1), (int) ($operatorData['level'] ?? -2));
$suite->equals('both screens agree on the question',
    (string) ($displayState['question']['text'] ?? 'display-missing'), (string) ($operatorData['question']['text'] ?? 'operator-missing'));
$suite->equals('both screens agree on the state', (string) ($displayState['state'] ?? 'display-missing'), (string) ($operatorData['state'] ?? 'operator-missing'));

$started = microtime(true);
$polled = json_decode(file_get_contents($baseUrl . '/api/display/state?v=' . $displayState['state_version']) ?: '{}', true);
$waited = microtime(true) - $started;
$suite->check('long-poll returns promptly when nothing changes', $waited < 4.0 && ($polled['success'] ?? false), sprintf('%.1fs', $waited));

// ---------------------------------------------------------------------------
// 10. Prize, guarantee and gift logic
// ---------------------------------------------------------------------------
$suite->module('10. Prize, guarantee and gift logic');
$maxLevel = (int) $db->scalar("SELECT MAX(level_no) FROM prize_levels WHERE status = 'active'");
$guaranteedLevel = (int) $db->scalar("SELECT MIN(level_no) FROM prize_levels WHERE is_guaranteed = 1 AND status = 'active'");
$guaranteedAmount = (float) $db->scalar('SELECT amount FROM prize_levels WHERE level_no = ?', [$guaranteedLevel]);

/** Answer the question currently on air correctly and return the new state. */
$answerCorrectly = static function () use ($api, $apiGet): array {
    $state = $apiGet('/api/game/state')['data'];
    $api('/api/game/select', ['option' => $state['private']['correct_option']]);
    $api('/api/game/lock');
    return $api('/api/game/reveal')['data'];
};

// Play correctly up to and including the guaranteed level.
$state = $apiGet('/api/game/state')['data'];
while ((int) $state['level'] < $guaranteedLevel && !$state['is_finished']) {
    if ($state['state'] === GameService::STATE_CORRECT) {
        $state = $api('/api/game/next')['data'];
        continue;
    }
    $state = $answerCorrectly();
}
$suite->equals('reached the guaranteed level', (int) $state['level'], $guaranteedLevel);

// The guarantee is banked by answering that level, not by arriving at it.
$beforeAnswering = (float) $state['prize']['guaranteed'];
if ($state['state'] !== GameService::STATE_CORRECT) {
    $state = $answerCorrectly();
}
$suite->check('guarantee is only banked once the level is answered',
    $beforeAnswering < $guaranteedAmount,
    sprintf('%s before answering level %d', number_format($beforeAnswering), $guaranteedLevel));
$suite->equals('guaranteed amount is banked after answering it', (float) $state['prize']['guaranteed'], $guaranteedAmount);

$giftLevel = (int) ($db->scalar('SELECT level_no FROM prize_levels WHERE gift_id IS NOT NULL AND level_no <= ? ORDER BY level_no LIMIT 1', [$guaranteedLevel]) ?? 0);
if ($giftLevel > 0) {
    $awarded = (int) $db->scalar('SELECT COUNT(*) FROM game_answers WHERE game_id = ? AND gift_id IS NOT NULL', [$gameId]);
    $suite->check('a gift was awarded on a gift level', $awarded > 0, $awarded . ' gift(s) recorded');
    $used = (int) $db->scalar('SELECT quantity_used FROM gifts WHERE id = (SELECT gift_id FROM prize_levels WHERE level_no = ?)', [$giftLevel]);
    $suite->check('gift stock decremented', $used > 0, 'quantity_used = ' . $used);
}

// A wrong answer at the next level must fall back to the guaranteed amount.
$state = $api('/api/game/next')['data'];
$suite->check('advanced past the guaranteed level', (int) ($state['level'] ?? 0) === $guaranteedLevel + 1,
    'now at level ' . ($state['level'] ?? '?'));
$wrong = '';
foreach (['A', 'B', 'C', 'D'] as $option) {
    if ($option !== ($state['private']['correct_option'] ?? '')) { $wrong = $option; break; }
}
$api('/api/game/select', ['option' => $wrong]);
$api('/api/game/lock');
$state = $api('/api/game/reveal')['data'];
$suite->equals('wrong answer gives state WRONG', (string) $state['state'], GameService::STATE_WRONG);
$suite->equals('game ends on a wrong answer', (string) $state['status'], 'wrong_answer');
$suite->equals('final prize falls back to the guaranteed amount', (float) $state['prize']['final_prize'], $guaranteedAmount);
$suite->check('display shows the final amount', (float) ($display()['prize']['final_prize'] ?? -1) === $guaranteedAmount, (string) ($display()['prize']['final_prize_label'] ?? ''));

// ---------------------------------------------------------------------------
// 11. Transaction safety
// ---------------------------------------------------------------------------
$suite->module('11. Transaction safety');
$engine = GameService::make();
$state = $engine->createGame($participantId, null);
$txGameId = (int) $state['game_id'];
$engine->startGame($txGameId);
$engine->startTimer($txGameId);
$state = $engine->state($txGameId, true);
$engine->selectOption($txGameId, (string) $state['private']['correct_option']);
$engine->lockAnswer($txGameId);

$before = $db->selectOne('SELECT state, current_winnings, questions_attempted FROM games WHERE id = ?', [$txGameId]);
$giftBefore = (int) ($db->scalar('SELECT COALESCE(SUM(quantity_used), 0) FROM gifts') ?? 0);

$db->pdo()->exec('ALTER TABLE game_answers ADD COLUMN verify_forced_failure INT NOT NULL');
$threw = false;
try { $engine->revealResult($txGameId); } catch (\Throwable) { $threw = true; }
$db->pdo()->exec('ALTER TABLE game_answers DROP COLUMN verify_forced_failure');

$after = $db->selectOne('SELECT state, current_winnings, questions_attempted FROM games WHERE id = ?', [$txGameId]);
$suite->check('a failure during reveal throws', $threw);
$suite->check('the game row is unchanged after rollback', $before == $after, 'state stayed ' . ($after['state'] ?? '?'));
$suite->equals('no orphan answer row', (int) $db->scalar('SELECT COUNT(*) FROM game_answers WHERE game_id = ?', [$txGameId]), 0);
$suite->equals('gift stock unchanged', (int) ($db->scalar('SELECT COALESCE(SUM(quantity_used), 0) FROM gifts') ?? 0), $giftBefore);
$state = $engine->state($txGameId, true);
$suite->equals('the game is still playable', (string) $state['state'], GameService::STATE_LOCKED);
$state = $engine->revealResult($txGameId);
$suite->equals('reveal succeeds once the fault is removed', (string) $state['state'], GameService::STATE_CORRECT);

// ---------------------------------------------------------------------------
// 12. Game reset and completion
// ---------------------------------------------------------------------------
$suite->module('12. Game reset and completion');
$auditBefore = (int) $db->scalar('SELECT COUNT(*) FROM audit_logs');
$state = $engine->resetGame($txGameId, false);
$suite->equals('reset clears the level', (int) $state['level'], 0);
$suite->equals('reset clears recorded answers', (int) $db->scalar('SELECT COUNT(*) FROM game_answers WHERE game_id = ?', [$txGameId]), 0);
$suite->equals('reset clears used lifelines', (int) $db->scalar('SELECT COUNT(*) FROM game_lifelines WHERE game_id = ?', [$txGameId]), 0);
$suite->check('reset preserves the audit log', (int) $db->scalar('SELECT COUNT(*) FROM audit_logs') > $auditBefore, 'audit rows kept and added');
$suite->equals('reset returns gift stock', (int) ($db->scalar('SELECT COALESCE(SUM(quantity_used), 0) FROM gifts') ?? 0), $giftBefore);

// Playing a full ladder repeatedly needs question reuse; the default is off,
// which is itself verified below.
$reuseWasOn = SettingsService::bool('repeat_questions', false);
$available = (new \App\Repositories\QuestionRepository())->availableCount(false);
$suite->check('unused questions are tracked across games',
    $available < (int) $db->scalar("SELECT COUNT(*) FROM questions WHERE status = 'active'"),
    $available . ' unused of ' . (int) $db->scalar("SELECT COUNT(*) FROM questions WHERE status = 'active'") . ' active');
SettingsService::set('repeat_questions', true);

$engine->startGame($txGameId);
for ($level = 1; $level <= $maxLevel; $level++) {
    $state = $engine->state($txGameId, true);
    if ($state['is_finished']) { break; }
    $engine->startTimer($txGameId);
    $engine->selectOption($txGameId, (string) $state['private']['correct_option']);
    $engine->lockAnswer($txGameId);
    $state = $engine->revealResult($txGameId);
    if (!$state['is_finished']) { $engine->nextQuestion($txGameId); }
}
$topPrize = (float) $db->scalar('SELECT amount FROM prize_levels WHERE level_no = ?', [$maxLevel]);
$suite->equals('clearing the ladder completes the game', (string) $state['status'], 'completed');
$suite->equals('final prize is the top prize', (float) $state['prize']['final_prize'], $topPrize);
$suite->equals('questions answered', (int) $state['stats']['correct'], $maxLevel);

SettingsService::set('repeat_questions', $reuseWasOn);
$suite->equals('question-reuse setting restored', SettingsService::bool('repeat_questions', true), $reuseWasOn);

// Time up
SettingsService::set('repeat_questions', true);
$state = $engine->createGame($participantId, null);
$timeGameId = (int) $state['game_id'];
$db->run('UPDATE questions SET time_limit = 1');
$engine->startGame($timeGameId);
$engine->startTimer($timeGameId);
usleep(1400000);
$state = $engine->state($timeGameId, true);
$suite->equals('timer expires without any client action', (string) $state['state'], GameService::STATE_TIME_UP);
$state = $engine->revealResult($timeGameId);
$suite->equals('time up is recorded as a timeout', (string) $db->scalar('SELECT result FROM game_answers WHERE game_id = ? ORDER BY id DESC LIMIT 1', [$timeGameId]), 'timeout');
$suite->equals('time up ends the game', (string) $state['status'], 'time_up');
$db->run('UPDATE questions SET time_limit = 30');
SettingsService::set('repeat_questions', $reuseWasOn);

// ---------------------------------------------------------------------------
// 13. Backup and restore
// ---------------------------------------------------------------------------
$suite->module('13. Backup and restore');
$backups = BackupService::make();
$backup = $backups->backupDatabase(null, 'verification suite');
$suite->check('database backup created', is_file($backup['path']), $backup['filename'] . ' (' . $backup['size_human'] . ')');

$zip = new ZipArchive();
$zip->open($backup['path']);
$dump = (string) $zip->getFromName('database.sql');
$hasManifest = $zip->getFromName('manifest.json') !== false;
$zip->close();
$suite->check('backup contains a manifest', $hasManifest);
$suite->check('dump contains every table', substr_count($dump, 'CREATE TABLE') >= count($tables) - 1, substr_count($dump, 'CREATE TABLE') . ' CREATE TABLE statements');

$sampleQuestion = $db->selectOne("SELECT id, question_text FROM questions WHERE question_text REGEXP '[^ -~]' LIMIT 1");
$questionCount = (int) $db->scalar('SELECT COUNT(*) FROM questions');
$db->run('DELETE FROM question_options');
$db->run('DELETE FROM game_answers');
$db->run('DELETE FROM game_questions');
$db->run('DELETE FROM questions');
$suite->equals('data destroyed before the restore', (int) $db->scalar('SELECT COUNT(*) FROM questions'), 0);

$restore = $backups->restoreDatabase($backup['path']);
$suite->equals('restore brings the rows back', (int) $db->scalar('SELECT COUNT(*) FROM questions'), $questionCount);
$suite->check('a safety backup was taken before restoring', $restore['safety_backup'] !== '', $restore['safety_backup']);
if ($sampleQuestion !== null) {
    $suite->equals('Gujarati text survives backup and restore byte for byte',
        (string) $db->scalar('SELECT question_text FROM questions WHERE id = ?', [(int) $sampleQuestion['id']]),
        (string) $sampleQuestion['question_text']);
}

$files = $backups->backupFiles(null, 'verification suite');
$suite->check('files backup created', is_file($files['path']), $files['filename'] . ' (' . $files['size_human'] . ', ' . $files['file_count'] . ' files)');
$zip = new ZipArchive();
$zip->open($files['path']);
$names = [];
for ($i = 0; $i < min($zip->numFiles, 5000); $i++) { $names[] = (string) $zip->getNameIndex($i); }
$zip->close();
$suite->check('files backup contains the application', in_array('index.php', $names, true));
$suite->check('files backup excludes existing backups',
    count(array_filter($names, static fn ($n) => str_starts_with($n, 'storage/backups/'))) === 0);
foreach ([$backup['id'], $files['id']] as $id) { $backups->delete((int) $id); }

// ---------------------------------------------------------------------------
// 14. Migrations, cache and the updater
// ---------------------------------------------------------------------------
$suite->module('14. Migrations, cache and the updater');
$migrations = MigrationService::make();
$suite->equals('no migrations are pending', $migrations->pending(), []);
$suite->check('migration history is recorded', count($migrations->applied()) >= 5, count($migrations->applied()) . ' applied');

CacheService::put('verify_probe', ['value' => 'present'], 60);
$suite->check('cache stores a value', (CacheService::get('verify_probe')['value'] ?? '') === 'present');
$uploadsBefore = iterator_count(new FilesystemIterator(\App\Core\Application::uploadPath('questions')));
CacheService::clearAll();
$suite->check('cache clear removes the value', CacheService::get('verify_probe') === null);
$suite->equals('cache clear leaves uploads alone',
    iterator_count(new FilesystemIterator(\App\Core\Application::uploadPath('questions'))), $uploadsBefore);
$suite->check('cache clear leaves the database alone', (int) $db->scalar('SELECT COUNT(*) FROM questions') === $questionCount);

$updater = UpdateService::make();
$protected = $updater->protectedPaths();
$reflection = new ReflectionMethod($updater, 'isProtected');
$reflection->setAccessible(true);
foreach ([
    '.env' => true, 'storage/logs/app.log' => true, 'public/uploads/questions/a.jpg' => true,
    'config/local.php' => true, 'app/Core/Database.php' => false, 'index.php' => false,
] as $path => $expected) {
    $suite->equals('protected path rule: ' . $path, $reflection->invoke($updater, $path, $protected), $expected);
}

$check = $updater->checkForUpdate();
$suite->check('update check runs without a repository configured',
    is_array($check) && array_key_exists('update_available', $check),
    $check['configured'] ? 'repository configured' : 'not configured (expected on a fresh install)');

$result = $api('/api/updates/cache/clear');
$suite->check('cache clear API works', ($result['success'] ?? false) === true, (string) ($result['message'] ?? ''));

// ---------------------------------------------------------------------------
// 15. Audit log
// ---------------------------------------------------------------------------
$suite->module('15. Audit log');
foreach (['login', 'game.created', 'game.answer_locked', 'game.result_revealed', 'game.reset', 'question.created'] as $action) {
    $count = (int) $db->scalar('SELECT COUNT(*) FROM audit_logs WHERE action = ?', [$action]);
    $suite->check('logged: ' . $action, $count > 0, $count . ' entries');
}
$withIp = (int) $db->scalar('SELECT COUNT(*) FROM audit_logs WHERE ip_address IS NOT NULL');
$suite->check('audit entries record an IP address', $withIp > 0, $withIp . ' entries with an IP');
$secretLeak = (int) $db->scalar("SELECT COUNT(*) FROM audit_logs WHERE meta LIKE '%ghp_%' OR meta LIKE '%password\":\"%'");
$suite->equals('no secrets written to the audit log', $secretLeak, 0);

// ---------------------------------------------------------------------------
// 16. Responsive markup
// ---------------------------------------------------------------------------
$suite->module('16. Responsive and accessibility markup');
foreach (['/admin' => 'Admin', '/operator' => 'Operator', '/display' => 'Display'] as $path => $label) {
    $http->get($path);
    $suite->check($label . ' declares a viewport', str_contains($http->body, 'name="viewport"'));
}
$css = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/admin.css'));
$suite->check('admin CSS has mobile breakpoints', substr_count($css, '@media') >= 3, substr_count($css, '@media') . ' media queries');
$suite->check('admin CSS sets 16px inputs on mobile (prevents iOS zoom)', str_contains($css, 'font-size: 16px'));
$displayCss = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/display.css'));
$suite->check('display CSS scales with the viewport (vmin units)', substr_count($displayCss, 'vmin') > 40, substr_count($displayCss, 'vmin') . ' vmin values');
$suite->check('display CSS respects reduced motion', str_contains($displayCss, 'prefers-reduced-motion'));

// ---------------------------------------------------------------------------
// Tidy up and report
// ---------------------------------------------------------------------------
$db->run('DELETE FROM participants WHERE name = ?', ['Verification Participant']);
$db->run('DELETE FROM game_events');
$db->run('DELETE FROM game_answers');
$db->run('DELETE FROM game_lifelines');
$db->run('DELETE FROM game_questions');
$db->run('DELETE FROM games');
$db->run("UPDATE gifts SET quantity_used = 0, status = 'active'");

$total = count($suite->results);
$failed = $suite->failures();
$passed = $total - $failed;

echo "\n" . str_repeat('=', 78) . "\n";
printf("  %d checks:  \033[32m%d passed\033[0m", $total, $passed);
if ($failed > 0) { printf(",  \033[31m%d failed\033[0m", $failed); }
echo "\n" . str_repeat('=', 78) . "\n";

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($suite->results as $row) {
        if (!$row['pass']) { printf("  - [%s] %s — %s\n", $row['module'], $row['test'], $row['detail']); }
    }
}

if (in_array('--markdown', $argv, true)) {
    $path = __DIR__ . '/../docs/verification-results.md';
    $out = "| Module | Test | Result | Status |\n|---|---|---|---|\n";
    foreach ($suite->results as $row) {
        $out .= sprintf("| %s | %s | %s | %s |\n",
            $row['module'], str_replace('|', '/', $row['test']),
            str_replace('|', '/', $row['detail'] ?: 'as expected'),
            $row['pass'] ? 'PASS' : 'FAIL');
    }
    file_put_contents($path, $out);
    echo "\nMarkdown table written to docs/verification-results.md\n";
}

exit($failed > 0 ? 1 : 0);
