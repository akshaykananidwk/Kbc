<?php
declare(strict_types=1);

/**
 * Verification for the features added after the first release: music,
 * media uploads, certificates, leaderboard, sponsors, QR audience voting,
 * public registration, Fastest Finger First, translations and rehearsal
 * mode.
 *
 * Included by tests/verify.php.
 *
 * @var Suite $suite
 * @var Http $http
 * @var App\Core\Database $db
 * @var string $baseUrl
 * @var int $participantId
 */

use App\Core\Lang;
use App\Services\AudiencePollService;
use App\Services\CertificateService;
use App\Services\FastestFingerService;
use App\Services\GameService;
use App\Services\LeaderboardService;
use App\Services\SettingsService;
use App\Support\QrCode;

$cleanGames = static function () use ($db): void {
    $db->run('DELETE FROM game_events');
    $db->run('DELETE FROM game_answers');
    $db->run('DELETE FROM game_lifelines');
    $db->run('DELETE FROM game_questions');
    $db->run('DELETE FROM certificates');
    $db->run('DELETE FROM audience_votes');
    $db->run('DELETE FROM audience_polls');
    $db->run('DELETE FROM games');
    $db->run("UPDATE gifts SET quantity_used = 0, status = 'active'");
};

// ---------------------------------------------------------------------------
// 17. Media settings upload
// ---------------------------------------------------------------------------
$suite->module('17. Media settings (the inline upload fix)');

$http->get('/admin/settings');
$settingsBody = $http->body;
$suite->check('sound settings render an upload control, not a text box',
    str_contains($settingsBody, 'data-media-field') && str_contains($settingsBody, 'data-key="sound_correct_answer"'),
    'inline media widget present');
$suite->check('music settings exist',
    str_contains($settingsBody, 'data-key="music_intro"') && str_contains($settingsBody, 'data-key="music_background"'),
    'intro and background music fields');
$suite->check('no free-text path box for a sound setting',
    !str_contains($settingsBody, 'type="text" id="set_sound_correct_answer"'),
    'old text input is gone');

// A genuine, tiny MP3 so the MIME sniffing is exercised for real.
$mp3 = "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xff\xfb\x90\x00" . str_repeat("\x00", 413), 20);
$mp3Path = sys_get_temp_dir() . '/verify-tone.mp3';
file_put_contents($mp3Path, $mp3);

$http->get('/admin/settings');
$token = $http->token();
$boundary = '----verify' . bin2hex(random_bytes(8));
$multipart = static function (array $fields, array $files, string $boundary): string {
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
    }
    foreach ($files as $name => [$filename, $type, $contents]) {
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n"
            . "Content-Type: {$type}\r\n\r\n{$contents}\r\n";
    }
    return $body . "--{$boundary}--\r\n";
};

$response = $http->request('POST', '/admin/settings/upload',
    $multipart(['_token' => $token, 'key' => 'music_intro'], ['file' => ['tone.mp3', 'audio/mpeg', $mp3]], $boundary),
    ['Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
);
$upload = json_decode($response, true) ?? [];
$suite->check('music uploads over AJAX', ($upload['success'] ?? false) === true, (string) ($upload['message'] ?? ''));
SettingsService::flush();   // the upload happened in the web process
$suite->check('the uploaded music is stored in its setting',
    SettingsService::string('music_intro') !== '', SettingsService::string('music_intro'));

$storedFile = \App\Core\Application::publicPath(SettingsService::string('music_intro'));
$suite->check('the file really exists on disk', is_file($storedFile), basename($storedFile));

$state = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$suite->check('the display API serves the music URL',
    ($state['settings']['music']['intro'] ?? '') !== '', (string) ($state['settings']['music']['intro'] ?? ''));

// A PHP file renamed to .mp3 must still be refused.
$http->get('/admin/settings');
$response = $http->request('POST', '/admin/settings/upload',
    $multipart(['_token' => $http->token(), 'key' => 'music_background'],
        ['file' => ['evil.mp3', 'audio/mpeg', '<?php echo "pwned";']], $boundary),
    ['Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
);
$bad = json_decode($response, true) ?? [];
$suite->check('a PHP file renamed to .mp3 is refused', ($bad['success'] ?? true) === false, (string) ($bad['message'] ?? ''));

$http->get('/admin/settings');
$response = $http->request('POST', '/admin/settings/remove-file',
    $multipart(['_token' => $http->token(), 'key' => 'music_intro'], [], $boundary),
    ['Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
);
$removed = json_decode($response, true) ?? [];
$suite->check('music can be removed again', ($removed['success'] ?? false) === true, '');
SettingsService::flush();
$suite->equals('the setting is cleared', SettingsService::string('music_intro'), '');
@unlink($mp3Path);

// ---------------------------------------------------------------------------
// 18. Built-in sound engine
// ---------------------------------------------------------------------------
$suite->module('18. Sound that works with no files');
$audioJs = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/audio.js'));
$appJs   = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/app.js'));
$suite->check('the audio engine ships', $audioJs !== '', strlen($audioJs) . ' bytes');
foreach (['question_start', 'correct_answer', 'wrong_answer', 'final_win', 'game_over', 'tick'] as $event) {
    $suite->check('synthesised tone defined: ' . $event, str_contains($audioJs, $event . ':'));
}
$suite->check('tones are generated, not sampled',
    str_contains($audioJs, 'createOscillator') && !str_contains($audioJs, '.mp3'),
    'Web Audio oscillators, no bundled audio files');
$suite->check('the display loads the audio engine',
    str_contains((string) $http->get('/display'), 'assets/js/audio.js'));

$http->get('/admin/settings');
$suite->check('the settings page loads the audio engine too',
    str_contains($http->body, 'assets/js/audio.js'));
$suite->check('every sound setting has a working Test sound button',
    substr_count($http->body, 'data-media-test') >= 6,
    substr_count($http->body, 'data-media-test') . ' test buttons');
$suite->check('the test button knows which built-in tone to play',
    str_contains($http->body, 'data-cue="correct_answer"'));
$suite->check('the tone player can be opened from a click',
    str_contains($audioJs, 'QuizAudio.prototype.unlockNow') && str_contains($appJs, 'tester.unlockNow()'),
    'unlockNow wired to the button');

// ---------------------------------------------------------------------------
// 19. Question media on the display
// ---------------------------------------------------------------------------
$suite->module('19. Question audio and video reach the display');
$displayJs = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/display.js'));
$suite->check('the display renders question video', str_contains($displayJs, "question.video"));
$suite->check('the display plays question audio', str_contains($displayJs, "question.audio"));

$cleanGames();
SettingsService::set('repeat_questions', true);
$questionId = (int) $db->scalar("SELECT id FROM questions WHERE status = 'active' ORDER BY id LIMIT 1");
$db->run('UPDATE questions SET audio_path = ? WHERE id = ?', ['uploads/questions/verify-tone.mp3', $questionId]);

$engine = GameService::make();
$state = $engine->createGame($participantId, null, null, false);
$gameId = (int) $state['game_id'];
$engine->startGame($gameId);
// Serve the question that carries the audio.
$db->run('UPDATE games SET current_question_id = ? WHERE id = ?', [$questionId, $gameId]);
$display = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$suite->check('question audio is in the display payload',
    str_contains((string) ($display['question']['audio'] ?? ''), 'verify-tone.mp3'),
    (string) ($display['question']['audio'] ?? 'missing'));
$db->run('UPDATE questions SET audio_path = NULL WHERE id = ?', [$questionId]);

// ---------------------------------------------------------------------------
// 20. Certificates
// ---------------------------------------------------------------------------
$suite->module('20. Winner certificates');
$cleanGames();
$state = $engine->createGame($participantId, null, null, false);
$gameId = (int) $state['game_id'];
$engine->startGame($gameId);
$maxLevel = (int) $db->scalar("SELECT MAX(level_no) FROM prize_levels WHERE status = 'active'");
for ($level = 1; $level <= $maxLevel; $level++) {
    $state = $engine->state($gameId, true);
    if ($state['is_finished']) { break; }
    $engine->startTimer($gameId);
    $engine->selectOption($gameId, (string) $state['private']['correct_option']);
    $engine->lockAnswer($gameId);
    $state = $engine->revealResult($gameId);
    if (!$state['is_finished']) { $engine->nextQuestion($gameId); }
}

$certificates = CertificateService::make();
$issued = $certificates->issue($gameId, 1);
$serial = (string) $issued['certificate']['serial_no'];
$suite->check('a certificate is issued for a finished game', $serial !== '', $serial);
$suite->equals('it carries the final prize',
    (float) $issued['certificate']['prize_amount'], (float) $state['prize']['final_prize']);

$reissued = $certificates->issue($gameId, 1);
$suite->equals('a reprint keeps the same serial number', (string) $reissued['certificate']['serial_no'], $serial);
$suite->check('the print count increases',
    (int) $reissued['certificate']['print_count'] > (int) $issued['certificate']['print_count'],
    'print ' . (int) $reissued['certificate']['print_count']);

$http->get('/admin/certificates/' . $gameId);
$suite->equals('the certificate page renders', $http->status, 200);
$suite->check('it shows the winner and the amount',
    str_contains($http->body, $serial) && str_contains($http->body, 'cert__prize-amount'),
    'serial and amount present');
$suite->check('it is laid out for A4 landscape printing',
    str_contains((string) file_get_contents(\App\Core\Application::publicPath('assets/css/certificate.css')), 'size: A4 landscape'));

$http->get('/admin/certificates');
$suite->equals('the certificates list renders', $http->status, 200);

// ---------------------------------------------------------------------------
// 21. Leaderboard and sponsors
// ---------------------------------------------------------------------------
$suite->module('21. Hall of fame and sponsors');
$board = LeaderboardService::make();
$top = $board->top(8);
$suite->check('the leaderboard lists the winner', $top !== [], count($top) . ' entries');
$suite->check('entries carry a prize label', ($top[0]['prize_label'] ?? '') !== '', (string) ($top[0]['prize_label'] ?? ''));

$summary = $board->summary();
$suite->check('the summary counts real games', (int) $summary['games'] > 0, $summary['games'] . ' games');

$db->run("DELETE FROM sponsors WHERE name = 'Verify Sponsor'");
$now = date('Y-m-d H:i:s');
$db->insert('sponsors', [
    'name' => 'Verify Sponsor', 'tagline' => 'Testing', 'tier' => 'title',
    'status' => 'active', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
]);

$display = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$suite->check('the idle screen is active after a finished game', ($display['idle']['active'] ?? false) === true);
$suite->check('sponsors reach the display',
    count(array_filter($display['idle']['sponsors'] ?? [], static fn ($s) => $s['name'] === 'Verify Sponsor')) === 1,
    count($display['idle']['sponsors'] ?? []) . ' sponsors');
$suite->check('the leaderboard reaches the display', ($display['idle']['leaderboard'] ?? []) !== []);
$db->run("DELETE FROM sponsors WHERE name = 'Verify Sponsor'");

$http->get('/admin/sponsors');
$suite->equals('the sponsors screen renders', $http->status, 200);

// ---------------------------------------------------------------------------
// 22. QR codes
// ---------------------------------------------------------------------------
$suite->module('22. QR codes');
$svg = QrCode::svg('https://example.com/vote/ABC123', 6);
$suite->check('a QR code is generated as SVG', str_starts_with($svg, '<svg') && strlen($svg) > 500, strlen($svg) . ' bytes');
$matrix = QrCode::matrix('https://example.com/vote/ABC123');
$suite->check('the matrix is square and sized to a real version',
    count($matrix) === count($matrix[0]) && (count($matrix) - 17) % 4 === 0,
    'version ' . ((count($matrix) - 17) / 4) . ', ' . count($matrix) . 'x' . count($matrix));
$suite->check('longer URLs pick a larger version',
    count(QrCode::matrix(str_repeat('https://a-long-domain.example.org/x/', 2))) > count($matrix));

foreach (['register', 'display'] as $target) {
    $http->get('/qr?for=' . $target);
    $suite->check('QR endpoint serves ' . $target,
        $http->status === 200 && str_contains($http->headers['content-type'] ?? '', 'image/svg+xml'),
        'HTTP ' . $http->status);
}
$http->get('/qr?for=nonsense');
$suite->equals('an unknown QR target is refused', $http->status, 404);

// ---------------------------------------------------------------------------
// 23. Live audience voting
// ---------------------------------------------------------------------------
$suite->module('23. Live audience voting');
$cleanGames();
$state = $engine->createGame($participantId, null, null, false);
$gameId = (int) $state['game_id'];
$state = $engine->startGame($gameId);
$correct = (string) $state['private']['correct_option'];

$state = $engine->openAudiencePoll($gameId);
$pollCode = (string) ($state['poll']['code'] ?? '');
$suite->check('voting opens with a short code', strlen($pollCode) === 6, $pollCode);
$suite->check('the code reaches the display',
    ((json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data']['poll']['code'] ?? '')) === $pollCode);

$phone = new Http($baseUrl);
$phone->get('/vote/' . $pollCode);
$suite->equals('the voting page opens on a phone', $phone->status, 200);
$phone->get('/vote/AB');
$suite->equals('a code of the wrong length is not routed at all', $phone->status, 404);
$phone->get('/vote/' . $pollCode);
$suite->check('the voting page never contains the answer',
    !str_contains($phone->body, 'correct_option') && !str_contains($phone->body, '"correct"'),
    'no answer in the markup');

$polls = AudiencePollService::make();
$tally = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
for ($i = 0; $i < 15; $i++) {
    $option = $i < 9 ? $correct : ['A', 'B', 'C', 'D'][$i % 4];
    $polls->vote($pollCode, $option, str_pad(dechex($i + 1), 32, '0', STR_PAD_LEFT), '127.0.0.1');
    $tally[$option]++;
}
$pollId = (int) $db->scalar('SELECT id FROM audience_polls WHERE code = ?', [$pollCode]);
$suite->equals('every phone is counted once', $polls->voteCount($pollId), 15);

$polls->vote($pollCode, 'D', str_pad('1', 32, '0', STR_PAD_LEFT), '127.0.0.1');
$suite->equals('a phone changing its mind does not add a vote', $polls->voteCount($pollId), 15);

$results = $polls->results($pollId);
$suite->equals('percentages total 100', array_sum($results['percentages']), 100);
$suite->equals('the results are marked as live', (string) $results['mode'], 'live');

$state = $engine->useLifeline($gameId, 'audience_poll');
$lifelines = [];
foreach ($state['lifelines'] as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$suite->equals('the lifeline uses the real votes', (string) ($lifelines['audience_poll']['result']['mode'] ?? ''), 'live');

// With no votes at all it must fall back rather than show an empty poll.
$cleanGames();
$state = $engine->createGame($participantId, null, null, false);
$gameId = (int) $state['game_id'];
$engine->startGame($gameId);
$engine->openAudiencePoll($gameId);
$state = $engine->useLifeline($gameId, 'audience_poll');
foreach ($state['lifelines'] as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$suite->check('with no votes the poll falls back to simulated',
    ($lifelines['audience_poll']['result']['mode'] ?? '') !== 'live',
    'mode ' . ($lifelines['audience_poll']['result']['mode'] ?? '?'));

// ---------------------------------------------------------------------------
// 24. Public registration
// ---------------------------------------------------------------------------
$suite->module('24. Public self-registration');
$db->run("DELETE FROM participants WHERE mobile = '9000000111'");

$public = new Http($baseUrl);
$public->get('/register');
$suite->equals('the registration page opens', $public->status, 200);

$body = $public->request('POST', '/register',
    http_build_query(['name' => 'Verify Registrant', 'mobile' => '9000000111', 'city' => 'Rajkot']),
    ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
$result = json_decode($body, true) ?? [];
$suite->check('a person can register themselves', ($result['success'] ?? false) === true, (string) ($result['data']['registration_no'] ?? ''));

$body = $public->request('POST', '/register',
    http_build_query(['name' => 'Verify Registrant', 'mobile' => '9000000111']),
    ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
$again = json_decode($body, true) ?? [];
$suite->check('registering twice does not duplicate', ($again['data']['duplicate'] ?? false) === true, 'same registration returned');
$suite->equals('only one row exists',
    (int) $db->scalar("SELECT COUNT(*) FROM participants WHERE mobile = '9000000111'"), 1);

$countBefore = (int) $db->scalar('SELECT COUNT(*) FROM participants');
$public->request('POST', '/register',
    http_build_query(['name' => 'Spam Bot', 'mobile' => '9000000222', 'website' => 'http://spam.example']),
    ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
$suite->equals('the honeypot absorbs bots silently',
    (int) $db->scalar('SELECT COUNT(*) FROM participants'), $countBefore);

$db->run("DELETE FROM participants WHERE mobile IN ('9000000111','9000000222')");

// ---------------------------------------------------------------------------
// 25. Fastest Finger First
// ---------------------------------------------------------------------------
$suite->module('25. Fastest Finger First');
$db->run('DELETE FROM fff_entries');
$db->run('DELETE FROM fff_rounds');

$contenders = array_map(static fn ($r) => (int) $r['id'], $db->select('SELECT id FROM participants ORDER BY id LIMIT 3'));
$fff = FastestFingerService::make();

if (count($contenders) >= 2) {
    $round = $fff->create('Put these in order', ['B', 'D', 'A', 'C'], $contenders, 1, 20);
    $roundId = (int) $round['id'];
    $suite->equals('a round is created', (string) $round['status'], 'pending');
    $suite->equals('every contender is entered', count($round['contenders']), count($contenders));
    $suite->check('the answer is withheld while pending', $round['correct_order'] === null);

    $fff->start($roundId);
    $operatorView = $fff->state($roundId, true);
    $codes = $operatorView['private']['access_codes'] ?? [];
    $suite->equals('each contender gets an access code', count($codes), count($contenders));
    $suite->check('codes are four characters',
        count(array_filter($codes, static fn ($c) => strlen((string) $c) === 4)) === count($codes));

    $codeValues = array_values($codes);
    usleep(250000);
    $fff->submitByCode($roundId, $codeValues[0], ['B', 'D', 'A', 'C']);   // correct, fast
    usleep(300000);
    $fff->submitByCode($roundId, $codeValues[1], ['A', 'B', 'C', 'D']);   // wrong, still quick
    usleep(250000);
    $public = $fff->submitByCode($roundId, $codeValues[2], ['B', 'D', 'A', 'C']); // correct, slower

    $suite->check('the public view has no private section', !array_key_exists('private', $public));
    $suite->check('the answer stays hidden while running', $public['correct_order'] === null);
    $suite->check('no answer is marked right or wrong while running',
        count(array_filter($public['contenders'], static fn ($c) => $c['is_correct'] !== null)) === 0);

    $closed = $fff->close($roundId);
    $suite->equals('the answer is released once closed', (string) $closed['correct_order'], 'BDAC');
    $suite->check('the fastest correct answer wins',
        ($closed['winner']['participant_id'] ?? 0) === $contenders[0],
        (string) ($closed['winner']['name'] ?? 'none'));

    $wrongEntry = null;
    foreach ($closed['contenders'] as $contender) {
        if ($contender['participant_id'] === $contenders[1]) { $wrongEntry = $contender; }
    }
    $suite->check('a fast but wrong answer never ranks',
        $wrongEntry !== null && $wrongEntry['rank'] === null && $wrongEntry['is_correct'] === false,
        'wrong answer excluded from the ranking');

    try {
        $fff->submitByCode($roundId, 'ZZZZ', ['A', 'B', 'C', 'D']);
        $suite->check('an invalid access code is refused', false, 'it was accepted');
    } catch (\App\Core\Exceptions\HttpException $e) {
        $suite->check('an invalid access code is refused', true, $e->getMessage());
    }

    $phone = new Http($baseUrl);
    $phone->get('/fff/' . $roundId);
    $suite->equals('the contender page opens', $phone->status, 200);
    $suite->check('the contender page never contains the answer', !str_contains($phone->body, 'BDAC'));
}

$http->get('/operator/fff');
$suite->equals('the Fastest Finger operator screen renders', $http->status, 200);
$db->run('DELETE FROM fff_entries');
$db->run('DELETE FROM fff_rounds');

// ---------------------------------------------------------------------------
// 26. Interface translations
// ---------------------------------------------------------------------------
$suite->module('26. Gujarati and Hindi interface');
$previousLocale = SettingsService::string('interface_language', 'en');

$suite->check('translations are available', count(Lang::available()) >= 3, implode(', ', Lang::available()));

Lang::use('gu');
$suite->equals('Gujarati translates a control', Lang::get('Start the game'), 'રમત શરૂ કરો');
Lang::use('hi');
$suite->equals('Hindi translates a control', Lang::get('Start the game'), 'खेल शुरू करें');
Lang::use('en');
$suite->equals('English falls through to the key', Lang::get('Start the game'), 'Start the game');
$suite->equals('an unknown key degrades to readable English',
    Lang::get('Some Untranslated Label'), 'Some Untranslated Label');

SettingsService::set('interface_language', 'gu');
$fresh = new Http($baseUrl);
$fresh->get('/admin/login');
$fresh->post('/admin/login', ['_token' => $fresh->token(), 'identifier' => $email, 'password' => $password]);
$fresh->get('/operator');
$suite->check('the operator screen renders in Gujarati',
    str_contains($fresh->body, 'રમત શરૂ કરો') && str_contains($fresh->body, 'જવાબ લૉક કરો'),
    'controls translated');
$fresh->get('/admin');
$suite->check('the admin navigation renders in Gujarati',
    str_contains($fresh->body, 'ડેશબોર્ડ') && str_contains($fresh->body, 'પ્રશ્નો'));

SettingsService::set('interface_language', $previousLocale);
Lang::use($previousLocale === '' ? 'en' : $previousLocale);

// ---------------------------------------------------------------------------
// 27. Rehearsal mode
// ---------------------------------------------------------------------------
$suite->module('27. Rehearsal mode');
$cleanGames();
$db->run('UPDATE questions SET times_used = 0, times_correct = 0, times_wrong = 0');

$giftsBefore = (int) ($db->scalar('SELECT COALESCE(SUM(quantity_used), 0) FROM gifts') ?? 0);
$usageBefore = (int) ($db->scalar('SELECT COALESCE(SUM(times_used), 0) FROM questions') ?? 0);

$state = $engine->createGame($participantId, null, null, true);
$rehearsalId = (int) $state['game_id'];
$suite->check('a game can be created as a rehearsal', ($state['is_rehearsal'] ?? false) === true);

$engine->startGame($rehearsalId);
for ($level = 1; $level <= min(4, $maxLevel); $level++) {
    $state = $engine->state($rehearsalId, true);
    if ($state['is_finished']) { break; }
    $engine->startTimer($rehearsalId);
    $engine->selectOption($rehearsalId, (string) $state['private']['correct_option']);
    $engine->lockAnswer($rehearsalId);
    $state = $engine->revealResult($rehearsalId);
    if (!$state['is_finished']) { $engine->nextQuestion($rehearsalId); }
}

$suite->equals('a rehearsal does not move gift stock',
    (int) ($db->scalar('SELECT COALESCE(SUM(quantity_used), 0) FROM gifts') ?? 0), $giftsBefore);
$suite->equals('a rehearsal does not skew question statistics',
    (int) ($db->scalar('SELECT COALESCE(SUM(times_used), 0) FROM questions') ?? 0), $usageBefore);

$games = new \App\Repositories\GameRepository();
$suite->equals('a rehearsal is hidden from game history', $games->paginate([], 1, 20)['total'], 0);
$suite->check('it can still be found on request', $games->paginate(['rehearsal' => 'only'], 1, 20)['total'] > 0);
$suite->equals('a rehearsal is excluded from the statistics', (int) ($games->statistics()['total_games'] ?? -1), 0);
$suite->equals('a rehearsal is excluded from the leaderboard', LeaderboardService::make()->top(8), []);

$engine->endGame($rehearsalId, 'completed');
try {
    CertificateService::make()->issue($rehearsalId, 1);
    $suite->check('a rehearsal gets no certificate', false, 'one was issued');
} catch (\App\Core\Exceptions\HttpException $e) {
    $suite->check('a rehearsal gets no certificate', str_contains($e->getMessage(), 'Rehearsal'), $e->getMessage());
}

$cleanGames();
$db->run('UPDATE questions SET times_used = 0, times_correct = 0, times_wrong = 0');
SettingsService::set('repeat_questions', false);
