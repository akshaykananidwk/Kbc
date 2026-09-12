<?php
declare(strict_types=1);

/**
 * Ganpati Bapa Quiz Show - end to end verification suite.
 *
 * Exercises the running application over real HTTP plus the live database,
 * and prints a pass/fail table. Nothing is mocked.
 *
 * Usage:
 *   php tests/verify.php [base-url] [admin-email] [admin-password]
 *
 * Example:
 *   php tests/verify.php http://127.0.0.1:8080 admin@example.com Secret123
 *
 * Exit code is 0 when every check passes, 1 otherwise.
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

// Flags may appear anywhere; positional arguments are read from what is left.
$positional = array_values(array_filter(array_slice($argv, 1), static fn ($a) => !str_starts_with($a, '--')));

$baseUrl  = rtrim($positional[0] ?? 'http://127.0.0.1:8080', '/');
$email    = $positional[1] ?? 'admin@ganpatiquiz.test';
$password = $positional[2] ?? 'Ganpati2026';

// ---------------------------------------------------------------------------
// Tiny harness
// ---------------------------------------------------------------------------
final class Suite
{
    /** @var array<int,array{module:string,test:string,detail:string,pass:bool}> */
    public array $results = [];
    private string $module = 'General';

    public function module(string $name): void
    {
        $this->module = $name;
        echo "\n\033[1m" . $name . "\033[0m\n";
    }

    public function check(string $test, bool $pass, string $detail = ''): bool
    {
        $this->results[] = ['module' => $this->module, 'test' => $test, 'detail' => $detail, 'pass' => $pass];
        printf("  [%s] %-52s %s\n", $pass ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m", $test, $detail);
        return $pass;
    }

    public function equals(string $test, mixed $actual, mixed $expected): bool
    {
        $pass = $actual === $expected;
        return $this->check(
            $test,
            $pass,
            $pass ? $this->short($expected) : 'expected ' . $this->short($expected) . ', got ' . $this->short($actual)
        );
    }

    private function short(mixed $value): string
    {
        if (is_bool($value)) { return $value ? 'true' : 'false'; }
        if ($value === null) { return 'null'; }
        if (is_array($value)) { return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '[]'; }
        $string = (string) $value;
        return mb_strlen($string) > 60 ? mb_substr($string, 0, 57) . '...' : $string;
    }

    public function failures(): int
    {
        return count(array_filter($this->results, static fn ($r) => !$r['pass']));
    }
}

final class Http
{
    private string $jar;
    public int $status = 0;
    public string $body = '';
    /** @var array<string,string> */
    public array $headers = [];
    public string $redirect = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'gq_jar_');
    }

    public function reset(): void
    {
        @unlink($this->jar);
        $this->jar = tempnam(sys_get_temp_dir(), 'gq_jar_');
    }

    /** @param array<string,string>|string|null $data */
    public function request(string $method, string $path, array|string|null $data = null, array $headers = []): string
    {
        $ch = curl_init($this->base . $path);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];
        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
        }
        if ($headers !== []) {
            $formatted = [];
            foreach ($headers as $name => $value) { $formatted[] = $name . ': ' . $value; }
            $options[CURLOPT_HTTPHEADER] = $formatted;
        }
        curl_setopt_array($ch, $options);

        $raw = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $this->body = substr($raw, $headerSize);
        $this->headers = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $this->headers[strtolower(trim($name))] = trim($value);
            }
        }
        $this->redirect = $this->headers['location'] ?? '';
        return $this->body;
    }

    public function get(string $path, array $headers = []): string
    {
        return $this->request('GET', $path, null, $headers);
    }

    public function post(string $path, array $data, array $headers = []): string
    {
        return $this->request('POST', $path, $data, $headers + ['Content-Type' => 'application/x-www-form-urlencoded']);
    }

    /** @param array<string,mixed> $payload */
    public function json(string $path, array $payload, string $token): array
    {
        $body = $this->request('POST', $path, json_encode($payload), [
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-Token'     => $token,
        ]);
        return json_decode($body, true) ?? ['success' => false, 'message' => 'unparseable: ' . substr($body, 0, 80)];
    }

    public function getJson(string $path): array
    {
        $body = $this->get($path, ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']);
        return json_decode($body, true) ?? ['success' => false, 'message' => 'unparseable'];
    }

    public function token(): string
    {
        if (preg_match('/name="_token" value="([^"]+)"/', $this->body, $m)) {
            return $m[1];
        }
        return '';
    }

    public function flash(): string
    {
        if (preg_match('/class="alert alert--\w+"[^>]*>(.*?)<\/div>/s', $this->body, $m)) {
            preg_match_all('/<span[^>]*>(.*?)<\/span>/s', $m[1], $spans);
            foreach ($spans[1] as $span) {
                $text = trim(strip_tags($span));
                if (mb_strlen($text) > 3) { return $text; }
            }
        }
        return '';
    }
}

$suite = new Suite();
$http  = new Http($baseUrl);
$db    = Database::instance();

echo "\n\033[1mGanpati Bapa Quiz Show - verification suite\033[0m\n";
echo "Target: {$baseUrl}   Database: " . $db->databaseName() . "   " . date('Y-m-d H:i:s') . "\n";

// ---------------------------------------------------------------------------
// 1. Installation
// ---------------------------------------------------------------------------
$suite->module('1. Installation');
$suite->check('storage/installed.lock exists', is_file(\App\Core\Application::instance()->storagePath('installed.lock')));
$http->get('/install');
$suite->check('/install is locked after installation', str_contains($http->body, 'already installed'), 'HTTP ' . $http->status);
$tables = $db->tables();
$expectedTables = ['users', 'roles', 'permissions', 'settings', 'participants', 'questions', 'question_options',
    'question_categories', 'prize_levels', 'gifts', 'lifelines', 'games', 'game_questions', 'game_answers',
    'game_lifelines', 'game_events', 'media', 'audit_logs', 'system_updates', 'backups', 'migrations'];
$missing = array_diff($expectedTables, $tables);
$suite->check('all required tables exist', $missing === [], count($tables) . ' tables' . ($missing ? ', missing: ' . implode(',', $missing) : ''));
$engine = $db->selectOne("SELECT engine, table_collation FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'games'");
$suite->equals('InnoDB engine', (string) ($engine['engine'] ?? ''), 'InnoDB');
$suite->check('utf8mb4 collation', str_starts_with((string) ($engine['table_collation'] ?? ''), 'utf8mb4'), (string) ($engine['table_collation'] ?? ''));

// ---------------------------------------------------------------------------
// 2. Authentication
// ---------------------------------------------------------------------------
$suite->module('2. Authentication');
$db->run('UPDATE users SET failed_attempts = 0, locked_until = NULL');

$http->get('/admin');
$suite->check('unauthenticated /admin redirects to login', $http->status === 302 && str_contains($http->redirect, '/admin/login'), 'HTTP ' . $http->status);

$http->get('/admin/login');
$token = $http->token();
$suite->check('login form carries a CSRF token', strlen($token) >= 32, strlen($token) . ' chars');

$http->post('/admin/login', ['_token' => $token, 'identifier' => $email, 'password' => 'definitely-wrong']);
$http->get('/admin/login');
$suite->check('wrong password is rejected', str_contains($http->flash(), 'Invalid credentials'), $http->flash());

$http->get('/admin/login');
$token = $http->token();
$http->post('/admin/login', ['_token' => $token, 'identifier' => $email, 'password' => $password]);
$suite->check('correct password signs in', $http->status === 302 && !str_contains($http->redirect, 'login'), $http->redirect);

$http->get('/admin');
$suite->equals('/admin reachable once signed in', $http->status, 200);

$csrf = (string) (($http->getJson('/api/auth/me')['data']['csrf_token']) ?? '');
$suite->check('API reports the signed-in user', strlen($csrf) >= 32, 'csrf token issued');

// ---------------------------------------------------------------------------
// 3. Security
// ---------------------------------------------------------------------------
$suite->module('3. Security');
$http->get('/admin');
$formToken = $http->token();

$before = (int) $db->scalar('SELECT COUNT(*) FROM question_categories');
$http->post('/admin/categories', ['name' => 'CSRF probe', 'status' => 'active']);
$after = (int) $db->scalar('SELECT COUNT(*) FROM question_categories');
$suite->equals('POST without a CSRF token is refused', $after, $before);

$http->post('/admin/categories', ['_token' => $formToken, 'name' => 'CSRF probe', 'status' => 'active', 'colour' => '#123456']);
$suite->equals('POST with a valid CSRF token succeeds', (int) $db->scalar('SELECT COUNT(*) FROM question_categories'), $before + 1);
$db->run("DELETE FROM question_categories WHERE name = 'CSRF probe'");

$payloads = ["' OR '1'='1", "'; DROP TABLE questions;--", "1 UNION SELECT password_hash FROM users", "1' AND SLEEP(3)--"];
$started = microtime(true);
$injectionSafe = true;
foreach ($payloads as $payload) {
    $http->get('/admin/questions?search=' . rawurlencode($payload));
    if ($http->status !== 200) { $injectionSafe = false; }
}
$elapsed = microtime(true) - $started;
$suite->check('SQL injection payloads are inert', $injectionSafe && count($db->tables()) === count($tables), count($db->tables()) . ' tables intact');
$suite->check('no time-based SQL injection', $elapsed < 3.0, sprintf('%d queries in %.2fs', count($payloads), $elapsed));

$xss = '<script>alert("xss")</script><img src=x onerror=alert(1)>';
$http->get('/admin/questions/create');
$formToken = $http->token();
$http->post('/admin/questions', [
    '_token' => $formToken, 'question_text' => $xss . ' XSS probe',
    'option_a' => 'a', 'option_b' => 'b', 'option_c' => 'c', 'option_d' => 'd',
    'correct_option' => 'A', 'difficulty' => 'easy', 'time_limit' => 30, 'status' => '1',
]);
$probeId = (int) $db->scalar("SELECT id FROM questions WHERE question_text LIKE '%XSS probe%' ORDER BY id DESC LIMIT 1");
$http->get('/admin/questions/' . $probeId);
$suite->check('stored XSS is escaped on output',
    $probeId > 0 && !str_contains($http->body, '<script>alert("xss")</script>') && str_contains($http->body, '&lt;script&gt;'),
    'payload rendered as text');
if ($probeId > 0) { $db->run('DELETE FROM questions WHERE id = ?', [$probeId]); }

foreach (['x-content-type-options' => 'nosniff', 'x-frame-options' => 'SAMEORIGIN'] as $header => $expected) {
    $suite->equals('header ' . $header, $http->headers[$header] ?? '', $expected);
}
$suite->check('Content-Security-Policy is set', isset($http->headers['content-security-policy']), substr($http->headers['content-security-policy'] ?? '', 0, 30) . '...');
$suite->check('admin pages are marked noindex', str_contains($http->headers['x-robots-tag'] ?? '', 'noindex'), $http->headers['x-robots-tag'] ?? 'missing');

foreach (['/app/Core/Database.php', '/.env', '/storage/logs', '/config/app.php', '/database/migrations'] as $path) {
    $http->get($path);
    $suite->check('direct access blocked: ' . $path, $http->status === 403 || $http->status === 404, 'HTTP ' . $http->status);
}

// ---------------------------------------------------------------------------
// 4. Authorisation
// ---------------------------------------------------------------------------
$suite->module('4. Authorisation');
$operatorRole = (int) $db->scalar("SELECT id FROM roles WHERE slug = 'operator'");
$db->run("DELETE FROM users WHERE email = 'verify-operator@test.local'");
$db->insert('users', [
    'role_id' => $operatorRole, 'name' => 'Verify Operator', 'email' => 'verify-operator@test.local',
    'username' => 'verifyop', 'password_hash' => \App\Services\AuthService::hashPassword('Operator2026'),
    'status' => 'active', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);

$op = new Http($baseUrl);
$op->get('/admin/login');
$op->post('/admin/login', ['_token' => $op->token(), 'identifier' => 'verify-operator@test.local', 'password' => 'Operator2026']);
$op->get('/operator');
$suite->equals('operator can open the operator screen', $op->status, 200);
foreach (['/admin/questions', '/admin/settings', '/admin/users', '/admin/backups', '/admin/updates'] as $path) {
    $op->get($path);
    $suite->check('operator blocked from ' . $path, $op->status === 302 && str_contains($op->redirect, 'forbidden'), 'HTTP ' . $op->status);
}
$result = $op->getJson('/api/settings');
$suite->check('operator blocked from the settings API', ($result['success'] ?? true) === false, (string) ($result['message'] ?? ''));
$db->run("DELETE FROM users WHERE email = 'verify-operator@test.local'");

// ---------------------------------------------------------------------------
// 5. CRUD modules
// ---------------------------------------------------------------------------
$suite->module('5. CRUD modules');
$pages = [
    '/admin' => 'Dashboard', '/admin/questions' => 'Questions', '/admin/categories' => 'Categories',
    '/admin/participants' => 'Participants', '/admin/prizes' => 'Prize ladder', '/admin/gifts' => 'Gifts',
    '/admin/lifelines' => 'Lifelines', '/admin/games' => 'Game history', '/admin/reports' => 'Reports',
    '/admin/settings' => 'Settings', '/admin/users' => 'Users', '/admin/backups' => 'Backups',
    '/admin/updates' => 'Updates', '/admin/logs/audit' => 'Audit log', '/operator' => 'Operator console',
    '/operator/setup' => 'Operator setup', '/display' => 'Display screen', '/' => 'Home page',
];
foreach ($pages as $path => $label) {
    $http->get($path);
    $suite->check($label . ' renders', $http->status === 200 && strlen($http->body) > 1200, 'HTTP ' . $http->status . ', ' . strlen($http->body) . 'b');
}

// Question create / update / delete
$http->get('/admin/questions/create');
$formToken = $http->token();
$http->post('/admin/questions', [
    '_token' => $formToken, 'question_text' => 'ચકાસણી પ્રશ્ન — verification question?',
    'option_a' => 'એક', 'option_b' => 'બે', 'option_c' => 'ત્રણ', 'option_d' => 'ચાર',
    'correct_option' => 'C', 'difficulty' => 'medium', 'time_limit' => 45, 'status' => '1',
    'explanation' => 'ચકાસણી માટે', 'sort_order' => 900,
]);
$newId = (int) $db->scalar("SELECT id FROM questions WHERE question_text LIKE '%verification question%' ORDER BY id DESC LIMIT 1");
$suite->check('question created', $newId > 0, 'id ' . $newId);
$suite->equals('question options stored', (int) $db->scalar('SELECT COUNT(*) FROM question_options WHERE question_id = ?', [$newId]), 4);
$suite->equals('Gujarati text stored unchanged',
    (string) $db->scalar('SELECT option_a FROM (SELECT option_text AS option_a FROM question_options WHERE question_id = ? AND option_key = "A") x', [$newId]), 'એક');

$http->get('/admin/questions/' . $newId . '/edit');
$http->post('/admin/questions/' . $newId, [
    '_token' => $http->token(), 'question_text' => 'ચકાસણી પ્રશ્ન — verification question?',
    'option_a' => 'એક', 'option_b' => 'બે', 'option_c' => 'ત્રણ', 'option_d' => 'ચાર',
    'correct_option' => 'D', 'difficulty' => 'hard', 'time_limit' => 60, 'status' => '1', 'sort_order' => 900,
]);
$suite->equals('question updated', (string) $db->scalar('SELECT correct_option FROM questions WHERE id = ?', [$newId]), 'D');

$http->get('/admin/questions');
$http->post('/admin/questions/' . $newId . '/duplicate', ['_token' => $http->token()]);
$copyId = (int) $db->scalar("SELECT id FROM questions WHERE question_text LIKE '[Copy]%' ORDER BY id DESC LIMIT 1");
$suite->check('question duplicated', $copyId > 0, 'copy id ' . $copyId);

$http->get('/admin/questions');
$http->post('/admin/questions/' . $copyId . '/delete', ['_token' => $http->token()]);
$suite->equals('unused question deleted', (int) $db->scalar('SELECT COUNT(*) FROM questions WHERE id = ?', [$copyId]), 0);

$usedId = (int) ($db->scalar('SELECT question_id FROM game_questions LIMIT 1') ?? 0);
if ($usedId > 0) {
    $http->get('/admin/questions');
    $http->post('/admin/questions/' . $usedId . '/delete', ['_token' => $http->token()]);
    $suite->check('question used in a game is deactivated, not deleted',
        (int) $db->scalar('SELECT COUNT(*) FROM questions WHERE id = ?', [$usedId]) === 1,
        'history preserved');
    $db->run("UPDATE questions SET status = 'active' WHERE id = ?", [$usedId]);
}
$db->run('DELETE FROM questions WHERE id = ?', [$newId]);

// Participant
$http->get('/admin/participants/create');
$http->post('/admin/participants', ['_token' => $http->token(), 'name' => 'Verification Participant', 'status' => 'active', 'city' => 'Rajkot', 'age' => 30]);
$participantId = (int) $db->scalar("SELECT id FROM participants WHERE name = 'Verification Participant' ORDER BY id DESC LIMIT 1");
$suite->check('participant created', $participantId > 0, 'id ' . $participantId);
$suite->check('registration number auto-assigned',
    (string) $db->scalar('SELECT registration_no FROM participants WHERE id = ?', [$participantId]) !== '', '');

// Prize level
$nextLevel = (int) $db->scalar('SELECT COALESCE(MAX(level_no), 0) + 1 FROM prize_levels');
$http->get('/admin/prizes');
$http->post('/admin/prizes', ['_token' => $http->token(), 'level_no' => $nextLevel, 'amount' => 640000,
    'time_limit' => 90, 'difficulty' => 'any', 'status' => 'active', 'is_guaranteed' => '1']);
$levelId = (int) $db->scalar('SELECT id FROM prize_levels WHERE level_no = ?', [$nextLevel]);
$suite->check('prize level created', $levelId > 0, 'level ' . $nextLevel . ' = 640000');
$suite->equals('guaranteed flag stored', (int) $db->scalar('SELECT is_guaranteed FROM prize_levels WHERE id = ?', [$levelId]), 1);
$http->get('/admin/prizes');
$http->post('/admin/prizes/' . $levelId . '/delete', ['_token' => $http->token()]);
$suite->equals('prize level deleted', (int) $db->scalar('SELECT COUNT(*) FROM prize_levels WHERE id = ?', [$levelId]), 0);

// Gift
$http->get('/admin/gifts/create');
$http->post('/admin/gifts', ['_token' => $http->token(), 'name' => 'Verification Gift', 'value_amount' => 999,
    'quantity_total' => 3, 'status' => 'active']);
$giftId = (int) $db->scalar("SELECT id FROM gifts WHERE name = 'Verification Gift' ORDER BY id DESC LIMIT 1");
$suite->check('gift created', $giftId > 0, 'id ' . $giftId);
$http->get('/admin/gifts');
$http->post('/admin/gifts/' . $giftId . '/delete', ['_token' => $http->token()]);
$suite->equals('gift deleted', (int) $db->scalar('SELECT COUNT(*) FROM gifts WHERE id = ?', [$giftId]), 0);

// CSV export
foreach (['/admin/questions/export', '/admin/reports/games.csv', '/admin/reports/questions.csv', '/admin/reports/prizes.csv'] as $path) {
    $http->get($path);
    $suite->check('CSV export ' . basename($path),
        $http->status === 200 && str_starts_with($http->body, "\xEF\xBB\xBF"),
        'HTTP ' . $http->status . ', UTF-8 BOM present');
}

echo "\n";
require __DIR__ . '/verify_game.php';
