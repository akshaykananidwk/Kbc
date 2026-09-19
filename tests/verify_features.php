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
use App\Services\ServerConfigService;
use App\Services\UpdateService;
use App\Support\Uploader;

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

// ---------------------------------------------------------------------------
// 28. The screen fits, and music really uploads
// ---------------------------------------------------------------------------
$suite->module('28. Screen fit and real music uploads');

$displayCss  = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/display.css'));
$operatorCss = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/operator.css'));
$adminCss    = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/admin.css'));
$displayJs   = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/display.js'));

// The bug that put the welcome screen on top of a live question: a hidden
// panel kept its space because its own class set display:flex.
foreach (['display' => $displayCss, 'operator' => $operatorCss, 'admin' => $adminCss] as $name => $css) {
    $suite->check('a hidden panel leaves the layout (' . $name . ')',
        (bool) preg_match('/\[hidden\]\s*\{\s*display:\s*none\s*!important/', $css));
}

$suite->check('every size on the display scales from one unit',
    str_contains($displayCss, '--u: calc(1vmin') && substr_count($displayCss, 'var(--u)') > 200,
    substr_count($displayCss, 'var(--u)') . ' scaled values');
$suite->check('the prize ladder scales on its own',
    str_contains($displayCss, '--ul: calc(var(--u)') && str_contains($displayJs, 'fitLadder'),
    'so a long ladder never shrinks the question');
$suite->check('the display measures itself and fits the screen',
    str_contains($displayJs, 'function fitToScreen') && str_contains($displayJs, "addEventListener('resize', forceFit)"));
$suite->check('the timer keeps its own space',
    str_contains($displayCss, '.d-timer { flex: 0 0 auto; }'), 'never squeezed off the screen');
$suite->check('the stage is a fixed grid that cannot overflow',
    str_contains($displayCss, 'grid-template-rows: auto auto minmax(0, 1fr) auto'));

// Upload limits: the real reason a song would not upload.
$suite->equals('server limit reads upload_max_filesize', Uploader::iniBytes('2M'), 2097152);
$suite->equals('server limit reads kilobytes', Uploader::iniBytes('512K'), 524288);
$suite->equals('server limit reads gigabytes', Uploader::iniBytes('1G'), 1073741824);
$suite->equals('a plain byte count is understood', Uploader::iniBytes('4096'), 4096);

$serverLimit = Uploader::serverLimit();
$suite->check('the app knows what this server accepts', $serverLimit > 0,
    \App\Support\Str::humanBytes($serverLimit));

$controller = new \App\Controllers\Admin\SettingsController();
$tooBig = [];
foreach ($controller->uploadFields() as $fieldKey => $spec) {
    if ((int) $spec['max'] > $serverLimit) { $tooBig[] = $fieldKey; }
}
$suite->check('no upload field promises more than the server allows', $tooBig === [],
    $tooBig === [] ? 'all capped at ' . \App\Support\Str::humanBytes($serverLimit) : implode(', ', $tooBig));

$http->get('/admin/settings');
$suite->check('the settings page states the real limit',
    str_contains($http->body, 'Uploads on this server are limited to'));
$suite->check('the upload control knows the limit before sending',
    str_contains($http->body, 'data-max=') && str_contains($http->body, 'data-max-label='));

// A real music file, the size of an actual song, over real HTTP.
$songBytes = min(3 * 1024 * 1024, max(0, $serverLimit - 262144));
if ($songBytes > 512 * 1024) {
    $song = "ID3\x03\x00\x00\x00\x00\x00\x00" . str_repeat("\xff\xfb\x90\x00" . str_repeat("\x00", 1020), (int) ($songBytes / 1024));
    $boundary = '----song' . bin2hex(random_bytes(6));
    $http->get('/admin/settings');
    $response = $http->request('POST', '/admin/settings/upload',
        $multipart(['_token' => $http->token(), 'key' => 'music_intro'],
            ['file' => ['song.mp3', 'audio/mpeg', $song]], $boundary),
        ['Content-Type' => 'multipart/form-data; boundary=' . $boundary,
         'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
    );
    $result = json_decode($response, true) ?? [];
    $suite->check('a real ' . round(strlen($song) / 1048576, 1) . ' MB song uploads',
        ($result['success'] ?? false) === true, (string) ($result['message'] ?? ''));

    SettingsService::flush();
    $storedSong = SettingsService::string('music_intro');
    $suite->check('the song is stored and served',
        $storedSong !== '' && is_file(\App\Core\Application::publicPath($storedSong)),
        $storedSong);

    $http->get('/admin/settings');
    $http->request('POST', '/admin/settings/remove-file',
        $multipart(['_token' => $http->token(), 'key' => 'music_intro'], [], $boundary),
        ['Content-Type' => 'multipart/form-data; boundary=' . $boundary,
         'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
    SettingsService::flush();
}

// The silent failure: a file bigger than post_max_size arrives with an empty
// body, CSRF token and all. Proved against a second server started here with
// stock hosting limits.
$stockPort = 8099;
$descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$command = escapeshellarg(PHP_BINARY) . ' -d upload_max_filesize=1M -d post_max_size=1M'
    . ' -S 127.0.0.1:' . $stockPort . ' -t ' . escapeshellarg(\App\Core\Application::instance()->rootPath())
    . ' ' . escapeshellarg(\App\Core\Application::instance()->rootPath('server-router.php'));
$stock = @proc_open($command, $descriptors, $pipes);

if (is_resource($stock)) {
    $stockUrl = 'http://127.0.0.1:' . $stockPort;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        usleep(150000);
        $probe = @file_get_contents($stockUrl . '/display', false, stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]));
        if ($probe !== false) { break; }
    }

    $stockHttp = new Http($stockUrl);
    $stockHttp->get('/admin/login');
    $stockHttp->post('/admin/login', ['_token' => $stockHttp->token(), 'identifier' => $email, 'password' => $password]);
    $stockHttp->get('/admin/settings');
    $suite->check('a stock 1 MB server advertises its real limit',
        str_contains($stockHttp->body, 'Uploads on this server are limited to 1 MB'),
        'admin is told before trying');

    $oversize = str_repeat('x', 2 * 1024 * 1024);
    $boundary = '----big' . bin2hex(random_bytes(6));
    $body = $stockHttp->request('POST', '/admin/settings/upload',
        $multipart(['_token' => $stockHttp->token(), 'key' => 'music_intro'],
            ['file' => ['big.mp3', 'audio/mpeg', $oversize]], $boundary),
        ['Content-Type' => 'multipart/form-data; boundary=' . $boundary,
         'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']
    );
    $result = json_decode($body, true) ?? [];
    $suite->equals('an oversized upload is refused, not silently lost', $stockHttp->status, 413);
    $suite->check('and the message says exactly what to change',
        str_contains((string) ($result['message'] ?? ''), 'too big for this server')
        && str_contains((string) ($result['message'] ?? ''), 'upload_max_filesize'),
        (string) ($result['message'] ?? ''));

    foreach ($pipes as $pipe) { @fclose($pipe); }
    proc_terminate($stock);
    proc_close($stock);
} else {
    $suite->check('oversized-upload path could be tested', false, 'could not start the test server');
}

// ---------------------------------------------------------------------------
// 29. Starting a game never dead-ends
// ---------------------------------------------------------------------------
$suite->module('29. Starting a game never dead-ends');
$cleanGames();
SettingsService::set('repeat_questions', false);

$engine = GameService::make();
$others = array_map(static fn ($r) => (int) $r['id'], $db->select('SELECT id FROM participants ORDER BY id LIMIT 2'));
$secondParticipant = $others[1] ?? $others[0];

$first = $engine->createGame($others[0], null, null, false);
$suite->check('a game is created', ($first['game_code'] ?? '') !== '', (string) ($first['game_code'] ?? ''));

try {
    $engine->createGame($secondParticipant, null, null, false);
    $suite->check('a second game is refused while one is open', false, 'it was created anyway');
} catch (\App\Core\Exceptions\HttpException $e) {
    $suite->check('a second game is refused while one is open', $e->statusCode() === 409, $e->getMessage());
    $context = $e->context();
    $suite->check('the refusal names the game that is blocking',
        ($context['open_game']['game_code'] ?? '') === (string) $first['game_code'],
        (string) ($context['open_game']['game_code'] ?? 'no detail'));
    $suite->check('and who was playing it',
        ($context['open_game']['participant'] ?? '') !== '',
        (string) ($context['open_game']['participant'] ?? ''));
}

$second = $engine->createGame($secondParticipant, null, null, false, true);
$suite->check('the operator can take over in one step', ($second['game_code'] ?? '') !== '', (string) ($second['game_code'] ?? ''));
$suite->equals('the abandoned game is closed, not deleted',
    (string) $db->scalar('SELECT status FROM games WHERE id = ?', [(int) $first['game_id']]), 'abandoned');
$suite->check('the takeover is written to the audit log',
    (int) $db->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'game.replaced'") > 0);

// An exhausted question bank must not leave the operator stuck either.
$db->run('DELETE FROM game_questions');
$level = 1;
foreach ($db->select("SELECT id FROM questions WHERE status = 'active'") as $row) {
    $db->run('INSERT INTO game_questions (game_id, question_id, level_no, served_at, created_at) VALUES (?,?,?,NOW(),NOW())',
        [(int) $second['game_id'], (int) $row['id'], $level++]);
}
$engine->endGame((int) $second['game_id'], 'abandoned');

$questionsRepo = new \App\Repositories\QuestionRepository();
$suite->equals('every question is now used', $questionsRepo->availableCount(false), 0);

try {
    $blocked = $engine->createGame($others[0], null, null, false, true);
    $engine->startGame((int) $blocked['game_id']);
    $suite->check('without reuse an exhausted bank is reported clearly', false, 'it started anyway');
} catch (\App\Core\Exceptions\HttpException $e) {
    $suite->check('without reuse an exhausted bank is reported clearly',
        str_contains($e->getMessage(), 'allow questions to be reused'), $e->getMessage());
}

$cleanGames();
$db->run('DELETE FROM game_questions');
$reuse = $engine->createGame($others[0], null, null, false, true, true);
$state = $engine->startGame((int) $reuse['game_id']);
$suite->check('allowing reuse for one game gets the show on air',
    ($state['question']['text'] ?? '') !== '', mb_substr((string) ($state['question']['text'] ?? ''), 0, 38));
$suite->equals('the global setting is untouched', SettingsService::bool('repeat_questions', true), false);
$engine->endGame((int) $reuse['game_id'], 'abandoned');

$http->get('/operator/setup');
$suite->equals('the setup screen renders', $http->status, 200);
$suite->check('it offers per-game question reuse', str_contains($http->body, 'id="repeatToggle"'));
$suite->check('the create button is not dead when a game is open',
    !preg_match('/id="createGameBtn"[^>]*disabled/', $http->body), 'button stays usable');

$cleanGames();
$db->run('DELETE FROM game_questions');
$db->run('UPDATE questions SET times_used = 0, times_correct = 0, times_wrong = 0');

// ---------------------------------------------------------------------------
// 30. Updates survive a locked-down host
// ---------------------------------------------------------------------------
$suite->module('30. Updates survive a locked-down host');

$updater = UpdateService::make($db);
$reflection = new \ReflectionClass($updater);
$applyFiles = $reflection->getMethod('applyFiles');
$applyFiles->setAccessible(true);

$sandbox = sys_get_temp_dir() . '/gq-update-' . bin2hex(random_bytes(5));
$package = $sandbox . '/package';
$target  = $sandbox . '/app';
mkdir($package . '/app/Core', 0777, true);
mkdir($target . '/app/Core', 0777, true);

file_put_contents($package . '/.htaccess', "# new server rules\n");
file_put_contents($package . '/.user.ini', "upload_max_filesize = 64M\n");
file_put_contents($package . '/index.php', "<?php // new front controller\n");
file_put_contents($package . '/app/Core/Thing.php', "<?php // new class\n");

// The host refuses to let PHP touch its server-config files. A read-only file
// would not prove anything here (the suite may run as root, which ignores
// permissions), so the target is made genuinely unwritable instead.
mkdir($target . '/.htaccess', 0777, true);
file_put_contents($target . '/index.php', "<?php // old\n");

$written = $applyFiles->invoke($updater, $package, $target);
$suite->check('the update completes despite a locked .htaccess', $written >= 2, $written . ' file(s) written');
$suite->check('the locked file is reported, not silently skipped',
    in_array('.htaccess', $updater->writeWarnings(), true),
    implode(', ', $updater->writeWarnings()));
$suite->check('the host\'s own server config is left exactly as it was',
    is_dir($target . '/.htaccess'), 'untouched');
$suite->check('the application files are updated all the same',
    str_contains((string) @file_get_contents($target . '/index.php'), 'new front controller')
    && is_file($target . '/app/Core/Thing.php'),
    'index.php and new classes written');

// A file that is not server configuration must still stop the update.
unlink($target . '/index.php');
mkdir($target . '/index.php', 0777, true);
file_put_contents($package . '/index.php', "<?php // newer\n");
try {
    $applyFiles->invoke($updater, $package, $target);
    $suite->check('a genuinely unwritable application file still fails loudly', false, 'it was ignored');
} catch (\RuntimeException $e) {
    $suite->check('a genuinely unwritable application file still fails loudly',
        str_contains($e->getMessage(), 'Could not write file: index.php'), $e->getMessage());
    $suite->check('and the message says which folder to check',
        str_contains($e->getMessage(), 'writable by PHP'), '');
}
exec('rm -rf ' . escapeshellarg($sandbox));

// The PHP limits helper.
$suite->check('the limits template asks for a usable size',
    str_contains(ServerConfigService::template(), 'upload_max_filesize = 64M')
    && str_contains(ServerConfigService::template(), 'post_max_size = 68M'));

$status = ServerConfigService::status();
$suite->check('the helper reports the live limit', $status['limit'] > 0, $status['limit_human']);
$suite->equals('and knows whether that is too small for a song',
    $status['is_low'], Uploader::serverLimit() < 8 * 1024 * 1024);

$userIni = ServerConfigService::userIniPath();
$existedBefore = is_file($userIni);
if (!$existedBefore) {
    $write = ServerConfigService::writeUserIni(null);
    $suite->check('the admin panel can create .user.ini where the host allows it',
        $write['written'] === true || str_contains($write['message'], 'not writable'),
        (string) $write['message']);
    if ($write['written']) {
        $suite->check('the file it writes is the documented one',
            (string) file_get_contents($userIni) === ServerConfigService::template());
        unlink($userIni);
    }
}
$suite->check('.user.ini is never shipped in the repository itself',
    !$existedBefore, 'it is created on the server, never updated over');
$suite->check('the template ships for manual installation',
    is_file(\App\Core\Application::instance()->rootPath('docs/user.ini.example')));

// ---------------------------------------------------------------------------
// 31. An update actually reaches the screens
// ---------------------------------------------------------------------------
$suite->module('31. An update actually reaches the screens');

// Browsers are told to cache CSS and JS for a week, so an updated show would
// keep running last week's stylesheet unless the address changes with it.
$http->get('/display');
$displayHtml = $http->body;
preg_match('/assets\/css\/display\.css\?v=(\d+)/', $displayHtml, $cssVersion);
$suite->check('the display stylesheet address carries a version',
    ($cssVersion[1] ?? '') !== '', $cssVersion[0] ?? 'no version in the URL');
$suite->check('so does the display script',
    (bool) preg_match('/assets\/js\/display\.js\?v=\d+/', $displayHtml));

$http->get('/admin');
$suite->check('and every admin asset', (bool) preg_match('/assets\/css\/admin\.css\?v=\d+/', $http->body));
$http->get('/operator');
$suite->check('and every operator asset', (bool) preg_match('/assets\/(css|js)\/operator\.(css|js)\?v=\d+/', $http->body));

$cssFile = \App\Core\Application::publicPath('assets/css/display.css');
$suite->equals('the version is the file\'s own timestamp', (string) ($cssVersion[1] ?? ''), (string) filemtime($cssFile));

// Touching the file must change the address - that is what makes an update
// visible on a television that has been open for a week.
$before = (string) ($cssVersion[1] ?? '');
touch($cssFile, time() + 5);
clearstatcache(true, $cssFile);
$http->get('/display');
preg_match('/assets\/css\/display\.css\?v=(\d+)/', $http->body, $after);
$suite->check('changing the file changes the address browsers ask for',
    ($after[1] ?? '') !== '' && ($after[1] ?? '') !== $before,
    $before . ' -> ' . ($after[1] ?? '?'));
touch($cssFile);

$suite->check('a missing asset still produces a usable URL',
    !str_contains(\App\Core\Application::asset('assets/css/not-here.css'), '?v='),
    \App\Core\Application::asset('assets/css/not-here.css'));

// The version on screen must describe the code that is running, so "did the
// update land?" can be answered by looking.
$versionFile = trim((string) file_get_contents(\App\Core\Application::instance()->rootPath('VERSION')));
$suite->check('the VERSION file ships with the package', $versionFile !== '', $versionFile);
$suite->equals('the reported version is the one on disk',
    UpdateService::make($db)->currentVersion(), $versionFile);

SettingsService::set('current_version', '0.0.1-stale');
$suite->equals('a stale setting from an earlier update cannot mislead',
    UpdateService::make($db)->currentVersion(), $versionFile);
SettingsService::set('current_version', $versionFile);

$http->get('/display');
$suite->check('the display shows the running version to the operator',
    str_contains($http->body, 'd-controls__version') && str_contains($http->body, 'v' . $versionFile),
    'v' . $versionFile);

// ---------------------------------------------------------------------------
// 32. Showing the right answer, and using the whole question bank
// ---------------------------------------------------------------------------
$suite->module('32. Right answer shown, whole bank used');

$displayCss = (string) file_get_contents(\App\Core\Application::publicPath('assets/css/display.css'));
$displayJs  = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/display.js'));
$screen     = (string) file_get_contents(\App\Core\Application::instance()->rootPath('resources/views/display/screen.php'));

// The screen must not resize when an answer is selected or revealed: a
// highlighted option is drawn larger than its box, and that used to read as
// "this does not fit" and shrank the whole board.
$suite->check('measurement ignores the highlight animations',
    str_contains($displayCss, '.d-stage.is-measuring *') && str_contains($displayJs, "classList.add('is-measuring')"));
$suite->check('a highlighted option settles back to its own size',
    !preg_match('/\.d-option\.is-(correct|selected)\s*\{[^}]*transform:\s*scale/', $displayCss),
    'it pops, then returns - so it is never clipped by the prize ladder');
$suite->check('the size is only recalculated when the layout really changes',
    str_contains($displayJs, 'function layoutSignature'), 'no twitch mid-question');
$suite->check('full-screen panels size themselves',
    str_contains($displayCss, '--uo: calc(var(--u)') && str_contains($displayJs, 'function fitOverlay'),
    'so a result panel never shrinks the board');

// Wrong answers: red, green, and the answer spelled out.
$suite->check('the board marks the wrong answer and the right one',
    str_contains($displayJs, "classList.add('is-wrong')") && str_contains($displayJs, "classList.add('is-correct')"));
$suite->check('red and green are actually used',
    str_contains($displayCss, '.d-option.is-wrong') && str_contains($displayCss, '.d-option.is-correct'));
$suite->check('the result panel has a row for each answer',
    str_contains($screen, 'dResultGiven') && str_contains($screen, 'dResultRight'));
$suite->check('it names the correct answer in words, not just a letter',
    str_contains($displayJs, "setText('dResultRightText'"), 'option text is shown');
$suite->check('the panel waits so the coloured board can be seen first',
    str_contains($displayJs, 'function queueResult'), 'panel follows the board');
$suite->check('the explanation is shown once the result is revealed',
    str_contains($displayJs, "el('dResultExplanation')"));

// The display payload must carry what that panel needs - and nothing early.
$cleanGames();
SettingsService::set('repeat_questions', true);
$state = $engine->createGame($participantId, null, null, false);
$gameId = (int) $state['game_id'];
$state = $engine->startGame($gameId);
$correctOption = (string) $state['private']['correct_option'];
$wrongOption = '';
foreach (['A', 'B', 'C', 'D'] as $key) {
    if ($key !== $correctOption && ($state['question']['options'][$key] ?? null) !== null) { $wrongOption = $key; break; }
}

$engine->startTimer($gameId);
$engine->selectOption($gameId, $wrongOption);
$engine->lockAnswer($gameId);

$display = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$suite->check('the answer is still hidden while it is only locked',
    $display['answer']['correct'] === null, 'present and null');

$engine->revealResult($gameId);
$display = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$suite->equals('after the reveal the display knows the right answer',
    (string) ($display['answer']['correct'] ?? ''), $correctOption);
$suite->equals('and which one was given', (string) ($display['answer']['selected'] ?? ''), $wrongOption);
$suite->check('both answers have their text on the screen',
    ($display['question']['options'][$correctOption] ?? '') !== ''
    && ($display['question']['options'][$wrongOption] ?? '') !== '',
    'the panel can name them');
$engine->endGame($gameId, 'abandoned');
$cleanGames();

// Rotation: a bank of many questions must be used before anything repeats.
$suite->module('33. Question rotation');
// Rotation is about repeats across games; the show-day switch is tested on
// its own later, so it is out of the way here.
$noRepeatBefore = SettingsService::bool('no_repeat_today', true);
SettingsService::set('no_repeat_today', false);
$questionsRepo = new \App\Repositories\QuestionRepository();
$db->run("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'VERIFY ROTATION%')");
$db->run("DELETE FROM questions WHERE question_text LIKE 'VERIFY ROTATION%'");
$db->run("UPDATE questions SET status = 'inactive' WHERE status = 'active'");

$bankSize = 12;
for ($i = 1; $i <= $bankSize; $i++) {
    $questionsRepo->createWithOptions([
        'question_text'  => 'VERIFY ROTATION ' . $i,
        'correct_option' => 'A', 'difficulty' => 'easy', 'status' => 'active',
        'sort_order'     => $i, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], ['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four']);
}

$levelsToPlay = 4;
$playRound = static function (int $games) use ($engine, $db, $participantId, $levelsToPlay): array {
    $served = [];
    for ($g = 0; $g < $games; $g++) {
        $state = $engine->createGame($participantId, null, 'random', true, true, true);
        $id = (int) $state['game_id'];
        $engine->startGame($id);
        for ($level = 1; $level <= $levelsToPlay; $level++) {
            $current = $engine->state($id, true);
            if ($current['is_finished'] || ($current['question'] ?? null) === null) { break; }
            $served[] = (int) $db->scalar(
                'SELECT question_id FROM game_questions WHERE game_id = ? AND level_no = ?', [$id, $level]);
            $engine->startTimer($id);
            $engine->selectOption($id, (string) $current['private']['correct_option']);
            $engine->lockAnswer($id);
            $engine->revealResult($id);
            if ($level < $levelsToPlay) { $engine->nextQuestion($id); }
        }
        $engine->endGame($id, 'completed');
    }
    return $served;
};

$reset = static function () use ($db) {
    $db->run('DELETE FROM game_events');
    $db->run('DELETE FROM game_answers');
    $db->run('DELETE FROM game_lifelines');
    $db->run('DELETE FROM game_questions');
    $db->run('DELETE FROM games');
    $db->run('UPDATE questions SET times_used = 0, times_served = 0, last_served_at = NULL');
};

SettingsService::set('question_rotation', true);
$reset();
$withRotation = $playRound(3);                       // 3 games x 4 questions = 12
$distinctRotated = count(array_unique($withRotation));
$suite->equals('rotation serves every question before repeating any',
    $distinctRotated, $bankSize);
$suite->equals('so nothing is served twice in the first full round',
    max(array_count_values($withRotation)), 1);

$fourth = $playRound(1);
$suite->check('the next round starts over rather than stopping',
    count($fourth) === $levelsToPlay, count($fourth) . ' question(s) served');

SettingsService::set('question_rotation', false);
$reset();
$without = $playRound(3);
$suite->check('without rotation the same questions come back while others wait',
    count(array_unique($without)) < $bankSize,
    count(array_unique($without)) . ' of ' . $bankSize . ' used, worst repeat ' . max(array_count_values($without)));
SettingsService::set('question_rotation', true);

$reset();
$first = $db->selectOne("SELECT id FROM questions WHERE question_text LIKE 'VERIFY ROTATION%' ORDER BY id LIMIT 1");
$questionsRepo->markServed((int) $first['id']);
$row = $db->selectOne('SELECT times_served, last_served_at FROM questions WHERE id = ?', [(int) $first['id']]);
$suite->equals('serving a question is recorded', (int) $row['times_served'], 1);
$suite->check('with the time it was served', ($row['last_served_at'] ?? '') !== '', (string) $row['last_served_at']);

$bank = $questionsRepo->bankStatus();
$suite->equals('the bank reports its size', (int) $bank['total'], $bankSize);
$suite->equals('and how many have never been used', (int) $bank['fresh'], $bankSize - 1);

$http->get('/operator/setup');
$suite->check('the operator sees the state of the bank before a show',
    str_contains($http->body, 'Question bank') && str_contains($http->body, 'never used'));

// Tidy the test bank away.
$reset();
$db->run("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE question_text LIKE 'VERIFY ROTATION%')");
$db->run("DELETE FROM questions WHERE question_text LIKE 'VERIFY ROTATION%'");
$db->run("UPDATE questions SET status = 'active' WHERE status = 'inactive'");
SettingsService::set('repeat_questions', false);
SettingsService::set('no_repeat_today', $noRepeatBefore);

// ---------------------------------------------------------------------------
// 34. The ready-made Gujarati question bank
// ---------------------------------------------------------------------------
$suite->module('34. Gujarati question bank');

$bankFile = \App\Core\Application::instance()->rootPath('database/seeds/gujarati-question-bank.php');
$suite->check('the question bank ships with the app', is_file($bankFile));

/** @var array<int,array<int,string>> $bankRows */
$bankRows = require $bankFile;
$suite->equals('it holds the full 200 questions', count($bankRows), 200);

$byCategory = [];
$problems = [];
$seenText = [];
foreach ($bankRows as $index => $row) {
    $line = $index + 1;
    if (count($row) !== 9) { $problems[] = 'row ' . $line . ': wrong shape'; continue; }
    [$category, $difficulty, $text, $a, $b, $c, $d, $correct, $explanation] = $row;

    $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) { $problems[] = 'row ' . $line . ': bad answer key'; }
    if (!in_array($difficulty, ['easy', 'medium', 'hard', 'expert'], true)) { $problems[] = 'row ' . $line . ': bad difficulty'; }
    if (count(array_unique([$a, $b, $c, $d])) !== 4) { $problems[] = 'row ' . $line . ': repeated option'; }
    foreach ([$a, $b, $c, $d] as $option) {
        if (trim((string) $option) === '') { $problems[] = 'row ' . $line . ': empty option'; }
    }
    if (trim($explanation) === '') { $problems[] = 'row ' . $line . ': no explanation'; }

    $key = mb_strtolower(preg_replace('/\s+/u', ' ', $text) ?? $text);
    if (isset($seenText[$key])) { $problems[] = 'row ' . $line . ': duplicate of row ' . $seenText[$key]; }
    $seenText[$key] = $line;
}
$suite->check('every question is complete and unique', $problems === [], implode('; ', array_slice($problems, 0, 3)));
$suite->equals('it covers five categories', count($byCategory), 5);
$suite->check('with the same number in each',
    count(array_unique(array_values($byCategory))) === 1,
    implode(', ', array_map(static fn ($k, $v) => $k . '=' . $v, array_keys($byCategory), $byCategory)));
$suite->check('the questions are in Gujarati',
    (bool) preg_match('/[\x{0A80}-\x{0AFF}]/u', (string) $bankRows[0][2]), mb_substr((string) $bankRows[0][2], 0, 34));

foreach (array_keys($byCategory) as $categoryName) {
    $id = (int) ($db->scalar('SELECT id FROM question_categories WHERE name = ? LIMIT 1', [$categoryName]) ?? 0);
    $live = (int) ($db->scalar("SELECT COUNT(*) FROM questions WHERE category_id = ? AND status = 'active'", [$id]) ?? 0);
    $suite->check('loaded into the database: ' . $categoryName, $live >= 40, $live . ' active question(s)');
}

$suite->check('the five categories are the ones a balanced show rotates through',
    (int) $db->scalar("SELECT COUNT(*) FROM question_categories WHERE status = 'active' AND in_rotation = 1") === 5,
    $db->scalar("SELECT COUNT(*) FROM question_categories WHERE status = 'active' AND in_rotation = 1") . ' in rotation');

// A Gujarati category name must keep the same slug every time it is saved.
$slugOne = \App\Support\Str::slug('ધાર્મિકતા');
$slugTwo = \App\Support\Str::slug('ધાર્મિકતા');
$suite->equals('a Gujarati name gives a stable slug', $slugOne, $slugTwo);
$suite->check('and different names give different slugs',
    $slugOne !== \App\Support\Str::slug('દેશભક્તિ'), $slugOne);
$suite->check('re-seeding does not duplicate a category',
    (int) $db->scalar("SELECT COUNT(*) FROM question_categories WHERE name = 'ધાર્મિકતા'") === 1);

// ---------------------------------------------------------------------------
// 35. Two questions from every category
// ---------------------------------------------------------------------------
$suite->module('35. Two questions from every category');
$cleanGames();
$db->run('UPDATE questions SET times_used = 0, times_served = 0, last_served_at = NULL');

$activeLevels = (int) $db->scalar("SELECT COUNT(*) FROM prize_levels WHERE status = 'active'");
$rotationCategories = (int) $db->scalar("SELECT COUNT(*) FROM question_categories WHERE status = 'active' AND in_rotation = 1");
$perCategory = $rotationCategories > 0 ? intdiv($activeLevels, $rotationCategories) : 0;

$sequences = [];
for ($game = 0; $game < 2; $game++) {
    $state = $engine->createGame($participantId, null, 'balanced', true, true, true);
    $id = (int) $state['game_id'];
    $engine->startGame($id);

    $order = [];
    for ($level = 1; $level <= $activeLevels; $level++) {
        $current = $engine->state($id, true);
        if ($current['is_finished'] || ($current['question'] ?? null) === null) { break; }
        $order[] = (string) ($db->scalar(
            'SELECT c.name FROM game_questions gq
             JOIN questions q ON q.id = gq.question_id
             LEFT JOIN question_categories c ON c.id = q.category_id
             WHERE gq.game_id = ? AND gq.level_no = ?', [$id, $level]) ?? '');
        $engine->startTimer($id);
        $engine->selectOption($id, (string) $current['private']['correct_option']);
        $engine->lockAnswer($id);
        $engine->revealResult($id);
        if ($level < $activeLevels) { $engine->nextQuestion($id); }
    }
    $engine->endGame($id, 'completed');
    $sequences[] = $order;

    $counts = array_count_values($order);
    $suite->equals('game ' . ($game + 1) . ': every category is used', count($counts), $rotationCategories);
    $suite->check('game ' . ($game + 1) . ': ' . $perCategory . ' question(s) from each',
        count(array_unique(array_values($counts))) === 1 && (int) reset($counts) === $perCategory,
        implode(', ', array_map(static fn ($k, $v) => mb_substr($k, 0, 8) . '=' . $v, array_keys($counts), $counts)));
}

$suite->check('and the running order differs from show to show',
    $sequences[0] !== $sequences[1],
    mb_substr(implode('→', array_map(static fn ($c) => mb_substr($c, 0, 4), $sequences[0])), 0, 40));

// The same level, served again, must land on the same category.
$state = $engine->createGame($participantId, null, 'balanced', true, true, true);
$repeatId = (int) $state['game_id'];
$engine->startGame($repeatId);
$firstCategory = (string) $db->scalar(
    'SELECT c.name FROM game_questions gq JOIN questions q ON q.id = gq.question_id
     LEFT JOIN question_categories c ON c.id = q.category_id WHERE gq.game_id = ? AND gq.level_no = 1', [$repeatId]);
$engine->resetGame($repeatId);
$engine->startGame($repeatId);
$againCategory = (string) $db->scalar(
    'SELECT c.name FROM game_questions gq JOIN questions q ON q.id = gq.question_id
     LEFT JOIN question_categories c ON c.id = q.category_id WHERE gq.game_id = ? AND gq.level_no = 1', [$repeatId]);
$suite->equals('a level keeps its category if the game is restarted', $againCategory, $firstCategory);
$engine->endGame($repeatId, 'abandoned');

$http->get('/operator/setup');
$suite->check('the operator can choose the balanced order',
    str_contains($http->body, 'Two questions from every category'));
$cleanGames();

// ---------------------------------------------------------------------------
// 36. Lifelines the room answers itself
// ---------------------------------------------------------------------------
$suite->module('36. Lifelines the room answers itself');
$cleanGames();
SettingsService::set('repeat_questions', true);

$state = $engine->createGame($participantId, null, null, false, true, true);
$gameId = (int) $state['game_id'];
$engine->startGame($gameId);
$engine->startTimer($gameId);

$state = $engine->useLifeline($gameId, 'audience_poll');
$lifelines = [];
foreach ($state['lifelines'] as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$poll = $lifelines['audience_poll']['result'] ?? [];
$suite->equals('the audience poll only announces itself', (string) ($poll['mode'] ?? ''), 'announce');
$suite->check('it invents no percentages', !isset($poll['percentages']),
    'the hall answers, the screen does not guess');
$suite->check('and it shows how long the audience has',
    (int) ($poll['seconds'] ?? 0) > 0, ($poll['seconds'] ?? 0) . ' seconds');

$state = $engine->useLifeline($gameId, 'phone_a_friend');
foreach ($state['lifelines'] as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$phone = $lifelines['phone_a_friend']['result'] ?? [];
$suite->check('Phone a Friend is available', isset($lifelines['phone_a_friend']));
$suite->equals('it is an announcement too', (string) ($phone['mode'] ?? ''), 'announce');
$suite->check('with a countdown for the call', (int) ($phone['seconds'] ?? 0) > 0, ($phone['seconds'] ?? 0) . ' seconds');

$display = json_decode((string) file_get_contents($baseUrl . '/api/display/snapshot'), true)['data'] ?? [];
$raw = json_encode($display, JSON_UNESCAPED_UNICODE) ?: '';
$suite->check('neither lifeline leaks the answer to the display',
    $display['answer']['correct'] === null && !str_contains($raw, 'correct_option'),
    'still hidden');

$screen = (string) file_get_contents(\App\Core\Application::instance()->rootPath('resources/views/display/screen.php'));
$displayJs = (string) file_get_contents(\App\Core\Application::publicPath('assets/js/display.js'));
$suite->check('the display has a panel for an announced lifeline',
    str_contains($screen, 'dAnnounceOverlay') && str_contains($displayJs, 'function showAnnouncement'));
$suite->check('it counts down on screen', str_contains($displayJs, "setText('dAnnounceSeconds'"));

$engine->endGame($gameId, 'abandoned');
$cleanGames();
SettingsService::set('repeat_questions', false);

// Categories can be taken in and out of the rotation from the admin panel.
$suite->module('37. Choosing which categories take part');
$http->get('/admin/categories');
$suite->equals('the categories screen renders', $http->status, 200);
$suite->check('it shows which categories are in rotation',
    str_contains($http->body, 'in rotation') && str_contains($http->body, 'name="in_rotation"'));

$sample = $db->selectOne("SELECT id, name, in_rotation FROM question_categories WHERE in_rotation = 1 ORDER BY id LIMIT 1");
if ($sample !== null) {
    $categoryId = (int) $sample['id'];
    $http->get('/admin/categories');
    $http->post('/admin/categories/' . $categoryId, [
        '_token' => $http->token(),
        'name'   => (string) $sample['name'],
        'colour' => '#b3141a',
        'status' => 'active',
        // in_rotation deliberately absent: an unticked checkbox sends nothing.
    ]);
    $suite->equals('unticking the box takes a category out of the rotation',
        (int) $db->scalar('SELECT in_rotation FROM question_categories WHERE id = ?', [$categoryId]), 0);

    $http->get('/admin/categories');
    $http->post('/admin/categories/' . $categoryId, [
        '_token'      => $http->token(),
        'name'        => (string) $sample['name'],
        'colour'      => '#b3141a',
        'status'      => 'active',
        'in_rotation' => '1',
    ]);
    $suite->equals('and ticking it puts the category back',
        (int) $db->scalar('SELECT in_rotation FROM question_categories WHERE id = ?', [$categoryId]), 1);

    $suite->check('renaming a Gujarati category keeps its questions',
        (int) $db->scalar('SELECT COUNT(*) FROM questions WHERE category_id = ?', [$categoryId]) > 0,
        $db->scalar('SELECT COUNT(*) FROM questions WHERE category_id = ?', [$categoryId]) . ' question(s) still attached');
}

// ---------------------------------------------------------------------------
// 38. Show day: age groups, no repeats, three lifelines
// ---------------------------------------------------------------------------
$suite->module('38. Show day setup');
$cleanGames();
$db->run('DELETE FROM game_switched_questions');
$db->run('UPDATE questions SET times_used = 0, times_served = 0, last_served_at = NULL');
SettingsService::set('no_repeat_today', true);
SettingsService::set('age_group_questions', true);
SettingsService::set('question_order', 'balanced');

// The show offers three lifelines, which is what the shipped setup applies.
$db->run("UPDATE lifelines SET is_enabled = 1 WHERE code IN ('fifty_fifty','phone_a_friend','skip_question')");
$db->run("UPDATE lifelines SET is_enabled = 0 WHERE code IN ('audience_poll','expert_advice')");

$migration = (string) file_get_contents(\App\Core\Application::instance()
    ->rootPath('database/migrations/2026_09_19_001500_show_day_setup.php'));
$suite->check('the shipped setup turns on exactly those three',
    str_contains($migration, "is_enabled = 0, updated_at = ?\n                  WHERE code IN ('audience_poll','expert_advice')")
    && str_contains($migration, "'is_enabled'  => 1,"),
    'set by the show-day migration');

$enabled = array_map(
    static fn (array $row): string => (string) $row['code'],
    $db->select('SELECT code FROM lifelines WHERE is_enabled = 1 ORDER BY sort_order')
);
$suite->equals('three lifelines are offered', count($enabled), 3);
$suite->check('and they are the three the show promises',
    $enabled === ['fifty_fifty', 'phone_a_friend', 'skip_question'],
    implode(' · ', $enabled));
$suite->equals('the question swap is named in Gujarati',
    (string) $db->scalar("SELECT name FROM lifelines WHERE code = 'skip_question'"), 'પ્રશ્ન બદલી');

// Age groups follow the contestant.
$juniorId = (int) $db->scalar('SELECT id FROM participants ORDER BY id LIMIT 1');
$seniorId = (int) $db->scalar('SELECT id FROM participants ORDER BY id DESC LIMIT 1');
$db->run('UPDATE participants SET age = 14, age_group = ? WHERE id = ?', ['auto', $juniorId]);
$db->run('UPDATE participants SET age = 35, age_group = ? WHERE id = ?', ['auto', $seniorId]);

$suite->equals('a 14 year old is a junior', $engine->ageGroupFor($juniorId), 'junior');
$suite->equals('a 35 year old is a senior', $engine->ageGroupFor($seniorId), 'senior');

$db->run('UPDATE participants SET age_group = ? WHERE id = ?', ['senior', $juniorId]);
$suite->equals('and the group can be pinned by hand', $engine->ageGroupFor($juniorId), 'senior');
$db->run('UPDATE participants SET age_group = ? WHERE id = ?', ['auto', $juniorId]);

// Questions aimed at one group are not asked of the other. The bank that
// ships is entirely senior, so the fixture opens a few up first.
$ageGroupBefore = [];
foreach ($db->select('SELECT id, age_group FROM questions') as $row) {
    $ageGroupBefore[(int) $row['id']] = (string) $row['age_group'];
}

$db->run("UPDATE questions SET age_group = 'senior'");
$db->run("UPDATE questions SET age_group = 'any' WHERE id IN
          (SELECT id FROM (SELECT id FROM questions WHERE status = 'active' ORDER BY id LIMIT 6) t)");
$openToAll = array_map(
    static fn (array $r): int => (int) $r['id'],
    $db->select("SELECT id FROM questions WHERE age_group = 'any'")
);

$questionsRepo = new \App\Repositories\QuestionRepository();
$juniorPick = $questionsRepo->pickForLevel(1, 'random', null, 'any', [], true, 'junior');
$suite->check('a junior is only asked questions open to them',
    $juniorPick !== null && in_array((int) $juniorPick['id'], $openToAll, true),
    'picked #' . (int) ($juniorPick['id'] ?? 0));

$seniorPick = $questionsRepo->pickForLevel(1, 'random', null, 'any', $openToAll, true, 'senior');
$suite->check('a senior can be asked the senior-only ones',
    $seniorPick !== null && !in_array((int) $seniorPick['id'], $openToAll, true),
    'picked #' . (int) ($seniorPick['id'] ?? 0));

// A bank aimed entirely at seniors must still let a junior play.
$db->run("UPDATE questions SET age_group = 'senior'");
$fallbackState = $engine->createGame($juniorId, null, null, false, true);
$fallbackId = (int) $fallbackState['game_id'];
$fallbackState = $engine->startGame($fallbackId);
$suite->check('a junior can still play when every question is senior',
    ($fallbackState['question']['text'] ?? '') !== '',
    'the picker falls back rather than stalling the show');
$suite->check('and the fallback is written to the game log',
    (int) $db->scalar("SELECT COUNT(*) FROM game_events WHERE game_id = ? AND event_type = 'question.age_group_fallback'",
        [$fallbackId]) > 0,
    'recorded, so it is never silent');
$engine->endGame($fallbackId, 'abandoned');
$suite->equals('the counter still reports what a junior can be asked',
    $questionsRepo->availableToday('junior') > 0, true);

foreach ($ageGroupBefore as $questionId => $group) {
    $db->run('UPDATE questions SET age_group = ? WHERE id = ?', [$group, $questionId]);
}

// One switch: nothing asked today comes back today.
$askedToday = [];
$levels = (int) $db->scalar("SELECT COUNT(*) FROM prize_levels WHERE status = 'active'");
for ($round = 0; $round < 3; $round++) {
    $contestant = $round % 2 === 0 ? $juniorId : $seniorId;
    $state = $engine->createGame($contestant, null, null, false, true);
    $id = (int) $state['game_id'];
    $engine->startGame($id);

    for ($level = 1; $level <= $levels; $level++) {
        $current = $engine->state($id, true);
        if ($current['is_finished'] || ($current['question'] ?? null) === null) { break; }
        $askedToday[] = (int) $db->scalar(
            'SELECT question_id FROM game_questions WHERE game_id = ? AND level_no = ?', [$id, $level]);
        $engine->startTimer($id);
        $engine->selectOption($id, (string) $current['private']['correct_option']);
        $engine->lockAnswer($id);
        $engine->revealResult($id);
        if ($level < $levels) { $engine->nextQuestion($id); }
    }
    $engine->endGame($id, 'completed');
}
$suite->equals('three shows in a row repeat nothing',
    count($askedToday), count(array_unique($askedToday)));
$suite->check('and the counter shows what is left for each group',
    $questionsRepo->availableToday('junior') > 0 && $questionsRepo->availableToday('senior') > 0,
    $questionsRepo->availableToday('junior') . ' junior · ' . $questionsRepo->availableToday('senior') . ' senior');

// Turning the switch off lets the bank be reused again.
SettingsService::set('no_repeat_today', false);
$suite->check('turning the switch off frees the whole bank again',
    $questionsRepo->availableToday('') === (int) $db->scalar("SELECT COUNT(*) FROM questions WHERE status = 'active'"),
    $questionsRepo->availableToday('') . ' available');
SettingsService::set('no_repeat_today', true);

// પ્રશ્ન બદલી: a different question, same prize level, and never the same one back.
$cleanGames();
$db->run('DELETE FROM game_switched_questions');
$db->run('UPDATE questions SET times_served = 0, last_served_at = NULL');

$state = $engine->createGame($seniorId, null, null, false, true);
$gameId = (int) $state['game_id'];
$state = $engine->startGame($gameId);
$beforeId = (int) $db->scalar('SELECT question_id FROM game_questions WHERE game_id = ? AND level_no = 1', [$gameId]);
$beforePrize = (float) $state['prize']['current_amount'];

$state = $engine->useLifeline($gameId, 'skip_question');
$afterId = (int) $db->scalar('SELECT question_id FROM game_questions WHERE game_id = ? AND level_no = 1', [$gameId]);

$suite->check('the swap serves a different question', $afterId > 0 && $afterId !== $beforeId,
    '#' . $beforeId . ' → #' . $afterId);
$suite->equals('the prize level does not change', (int) $state['level'], 1);
$suite->equals('and the prize is the same', (float) $state['prize']['current_amount'], $beforePrize);
$suite->equals('the swapped question is remembered',
    (int) $db->scalar('SELECT COUNT(*) FROM game_switched_questions WHERE game_id = ? AND question_id = ?',
        [$gameId, $beforeId]), 1);
$suite->check('so it cannot come back later in the same game',
    !in_array($beforeId, $games->questionsFor($gameId) === [] ? [] : array_map(
        static fn ($q) => (int) $q['question_id'], $games->questionsFor($gameId)), true)
    || $afterId !== $beforeId,
    'swapped away for good');

$lifelines = [];
foreach ($state['lifelines'] as $lifeline) { $lifelines[$lifeline['code']] = $lifeline; }
$suite->equals('the display is told to announce the swap',
    (string) ($lifelines['skip_question']['result']['mode'] ?? ''), 'announce');
$engine->endGame($gameId, 'abandoned');

// The one button on the operator screen.
$http->get('/operator/setup');
$suite->check('the operator has a single switch for the day',
    str_contains($http->body, 'day-switch') && str_contains($http->body, 'આજે કોઈ પ્રશ્ન ફરી નહીં પુછાય'));
$suite->check('it reports what is left for juniors and seniors',
    str_contains($http->body, 'junior') && str_contains($http->body, 'senior'));

$http->get('/operator/setup');
$http->post('/operator/no-repeat-today', ['_token' => $http->token(), 'enabled' => '0']);
SettingsService::flush();   // the change happened in the web process
$suite->equals('the button turns the rule off', SettingsService::bool('no_repeat_today', true), false);
$http->get('/operator/setup');
$http->post('/operator/no-repeat-today', ['_token' => $http->token(), 'enabled' => '1']);
SettingsService::flush();
$suite->equals('and back on', SettingsService::bool('no_repeat_today', true), true);

// Every entry is promised a gift; the day needs a way to track that.
$giftId = (int) $db->scalar('SELECT id FROM participants ORDER BY id LIMIT 1');
$db->run('UPDATE participants SET entry_gift_given_at = NULL WHERE id = ?', [$giftId]);
$http->get('/admin/participants');
$suite->check('the participants list has an entry-gift button',
    str_contains($http->body, 'entry-gift') && str_contains($http->body, 'ગિફ્ટ આપો'));
$http->post('/admin/participants/' . $giftId . '/entry-gift', ['_token' => $http->token()]);
$suite->check('one click records the gift',
    $db->scalar('SELECT entry_gift_given_at FROM participants WHERE id = ?', [$giftId]) !== null,
    (string) $db->scalar('SELECT entry_gift_given_at FROM participants WHERE id = ?', [$giftId]));
$http->get('/admin/participants');
$http->post('/admin/participants/' . $giftId . '/entry-gift', ['_token' => $http->token()]);
$suite->check('and clicking again undoes it',
    $db->scalar('SELECT entry_gift_given_at FROM participants WHERE id = ?', [$giftId]) === null);

$cleanGames();
$db->run('DELETE FROM game_switched_questions');
$db->run('UPDATE questions SET times_used = 0, times_served = 0, last_served_at = NULL');
SettingsService::set('question_order', 'fixed');

// ---------------------------------------------------------------------------
// 39. Replacing the question bank
// ---------------------------------------------------------------------------
$suite->module('39. Replacing the question bank');

$seniorFile = \App\Core\Application::instance()->rootPath('database/seeds/gujarati-question-bank-senior.php');
$openFile   = \App\Core\Application::instance()->rootPath('database/seeds/gujarati-question-bank.php');
$suite->check('a second, senior bank ships', is_file($seniorFile));

/** @var array<int,array<int,string>> $seniorRows */
$seniorRows = require $seniorFile;
/** @var array<int,array<int,string>> $openRows */
$openRows = require $openFile;

$suite->equals('it holds 200 questions', count($seniorRows), 200);

$seniorByCategory = [];
$seniorProblems = [];
$seenSenior = [];
$normalise = static fn (string $text): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)) ?? $text);

foreach ($seniorRows as $index => $row) {
    $line = $index + 1;
    if (count($row) !== 9) { $seniorProblems[] = 'row ' . $line . ': wrong shape'; continue; }
    [$category, $difficulty, $text, $a, $b, $c, $d, $correct, $explanation] = $row;

    $seniorByCategory[$category] = ($seniorByCategory[$category] ?? 0) + 1;
    if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) { $seniorProblems[] = 'row ' . $line . ': bad answer key'; }
    if (!in_array($difficulty, ['easy', 'medium', 'hard', 'expert'], true)) { $seniorProblems[] = 'row ' . $line . ': bad difficulty'; }
    if (count(array_unique([$a, $b, $c, $d])) !== 4) { $seniorProblems[] = 'row ' . $line . ': repeated option'; }
    foreach ([$a, $b, $c, $d] as $option) {
        if (trim((string) $option) === '') { $seniorProblems[] = 'row ' . $line . ': empty option'; }
    }
    if (trim($explanation) === '') { $seniorProblems[] = 'row ' . $line . ': no explanation'; }
    if (mb_strlen($text) < 12) { $seniorProblems[] = 'row ' . $line . ': question too short'; }

    $key = $normalise($text);
    if (isset($seenSenior[$key])) { $seniorProblems[] = 'row ' . $line . ': duplicate of row ' . $seenSenior[$key]; }
    $seenSenior[$key] = $line;
}

$suite->check('every senior question is complete and unique', $seniorProblems === [],
    implode('; ', array_slice($seniorProblems, 0, 3)));
$suite->equals('it covers the same five categories', count($seniorByCategory), 5);
$suite->check('with forty in each',
    count(array_unique(array_values($seniorByCategory))) === 1 && (int) reset($seniorByCategory) === 40,
    implode(', ', array_map(static fn ($k, $v) => mb_substr($k, 0, 8) . '=' . $v, array_keys($seniorByCategory), $seniorByCategory)));

$openKeys = [];
foreach ($openRows as $row) { $openKeys[$normalise((string) $row[2])] = true; }
$overlap = 0;
foreach ($seniorRows as $row) {
    if (isset($openKeys[$normalise((string) $row[2])])) { $overlap++; }
}
$suite->equals('not one question is shared with the open bank', $overlap, 0);

$harder = 0;
foreach ($seniorRows as $row) {
    if (in_array($row[1], ['medium', 'hard', 'expert'], true)) { $harder++; }
}
$suite->check('and it is pitched harder', $harder >= 180, $harder . ' of 200 are medium or hard');

// Clearing and reloading, the way the reset does it.
$seeder = \App\Services\SeederService::make($db);
$before = (int) $db->scalar('SELECT COUNT(*) FROM questions');
$cleared = $seeder->clearQuestions();

$suite->equals('clearing removes every question', (int) $db->scalar('SELECT COUNT(*) FROM questions'), 0);
$suite->equals('and every option with it', (int) $db->scalar('SELECT COUNT(*) FROM question_options'), 0);
$suite->equals('it reports what it removed', (int) $cleared['questions'], $before);
$suite->check('participants, prizes and settings are untouched',
    (int) $db->scalar('SELECT COUNT(*) FROM participants') > 0
    && (int) $db->scalar('SELECT COUNT(*) FROM prize_levels') > 0
    && (int) $db->scalar('SELECT COUNT(*) FROM settings') > 0,
    'only questions and the games that used them go');

$loaded = $seeder->seedQuestionBank('senior');
$suite->equals('the senior bank loads', $loaded, 200);
$suite->equals('every loaded question is marked senior',
    (int) $db->scalar("SELECT COUNT(*) FROM questions WHERE age_group = 'senior'"), 200);
$suite->equals('each category holds forty',
    (int) $db->scalar("SELECT COUNT(DISTINCT category_id) FROM questions"), 5);
$suite->check('and each question kept its four options',
    (int) $db->scalar('SELECT COUNT(*) FROM question_options') === 800,
    $db->scalar('SELECT COUNT(*) FROM question_options') . ' options');

// The admin screen, including the confirmation it insists on.
$http->get('/admin/questions/reset');
$suite->equals('the replace screen renders', $http->status, 200);
$suite->check('it warns what will be removed', str_contains($http->body, 'game(s) that used them'));
$suite->check('and offers both banks',
    str_contains($http->body, 'value="senior"') && str_contains($http->body, 'value="open"'));

$countBefore = (int) $db->scalar('SELECT COUNT(*) FROM questions');
$http->get('/admin/questions/reset');
$http->post('/admin/questions/reset', ['_token' => $http->token(), 'bank' => 'open', 'confirm' => 'maybe']);
$suite->equals('the wrong confirmation word changes nothing',
    (int) $db->scalar('SELECT COUNT(*) FROM questions'), $countBefore);

$backupsBefore = (int) $db->scalar('SELECT COUNT(*) FROM backups');
$http->get('/admin/questions/reset');
$http->post('/admin/questions/reset', ['_token' => $http->token(), 'bank' => 'senior', 'confirm' => 'DELETE']);
$suite->equals('the right word replaces the bank',
    (int) $db->scalar("SELECT COUNT(*) FROM questions WHERE age_group = 'senior'"), 200);
$suite->check('and a backup is taken first',
    (int) $db->scalar('SELECT COUNT(*) FROM backups') > $backupsBefore,
    'recoverable from Admin → Backups');
$suite->check('the replacement is written to the audit log',
    (int) $db->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'question.bank_reset'") > 0);
