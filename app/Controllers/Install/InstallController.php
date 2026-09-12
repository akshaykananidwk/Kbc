<?php
declare(strict_types=1);

namespace App\Controllers\Install;

use App\Controllers\Controller;
use App\Core\Application;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuthService;
use App\Services\MigrationService;
use App\Services\RequirementsService;
use App\Services\SeederService;
use App\Services\SettingsService;
use App\Support\Crypto;
use PDO;
use RuntimeException;

/**
 * Six step installation wizard.
 *
 * Nothing here requires editing a PHP file by hand: the wizard writes .env,
 * creates the schema, seeds defaults, creates the administrator and finally
 * writes storage/installed.lock so /install can never run again.
 */
final class InstallController extends Controller
{
    private const STEPS = [
        1 => ['key' => 'requirements', 'label' => 'System check'],
        2 => ['key' => 'database',     'label' => 'Database'],
        3 => ['key' => 'admin',        'label' => 'Administrator'],
        4 => ['key' => 'website',      'label' => 'Website'],
        5 => ['key' => 'run',          'label' => 'Install'],
        6 => ['key' => 'complete',     'label' => 'Finished'],
    ];

    public function index(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }
        return $this->redirect('/install/requirements');
    }

    // --- Step 1 ------------------------------------------------------

    public function requirements(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }

        $check = RequirementsService::check();

        return $this->installView('install.requirements', 1, [
            'check' => $check,
        ]);
    }

    // --- Step 2 ------------------------------------------------------

    public function database(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }
        if (!RequirementsService::check()['can_continue']) {
            $this->error('Please resolve the required system checks first.');
            return $this->redirect('/install/requirements');
        }

        return $this->installView('install.database', 2, [
            'data' => Session::get('install.database', [
                'host'     => '127.0.0.1',
                'port'     => '3306',
                'database' => '',
                'username' => '',
                'password' => '',
            ]),
        ]);
    }

    public function saveDatabase(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }

        $data = Validator::validate($request->all(), [
            'host'     => 'required|string|max:190',
            'port'     => 'required|int|between:1,65535',
            'database' => 'required|string|max:64|regex:^[A-Za-z0-9_\-]+$',
            'username' => 'required|string|max:64',
        ], [
            'database' => 'Database name',
            'username' => 'Database username',
        ]);

        $password = (string) $request->input('password', '');
        $config = [
            'driver'   => 'mysql',
            'host'     => $data['host'],
            'port'     => (string) $data['port'],
            'database' => $data['database'],
            'username' => $data['username'],
            'password' => $password,
            'charset'  => 'utf8mb4',
        ];

        // Try to connect; offer to create the database if it does not exist.
        try {
            $this->testConnection($config, $request->bool('create_database', false));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            Session::put('install.database', array_merge($config, ['password' => '']));
            Session::flashInput($request->all());
            return $this->redirect('/install/database');
        }

        Session::put('install.database', $config);
        $this->success('Database connection successful.');
        return $this->redirect('/install/admin');
    }

    // --- Step 3 ------------------------------------------------------

    public function admin(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }
        if (!Session::has('install.database')) {
            return $this->redirect('/install/database');
        }

        return $this->installView('install.admin', 3, [
            'data' => Session::get('install.admin', ['name' => '', 'email' => '', 'username' => 'admin']),
        ]);
    }

    public function saveAdmin(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }

        $data = Validator::validate($request->all(), [
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190',
            'username' => 'nullable|string|max:60|alpha_dash',
        ], ['name' => 'Administrator name']);

        $password = (string) $request->input('password', '');
        $policy = AuthService::passwordPolicy($password);
        if (!$policy['ok']) {
            $this->error($policy['message']);
            Session::flashInput($request->all());
            return $this->redirect('/install/admin');
        }
        if ($password !== (string) $request->input('password_confirmation', '')) {
            $this->error('The passwords do not match.');
            Session::flashInput($request->all());
            return $this->redirect('/install/admin');
        }

        Session::put('install.admin', [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'username' => ($data['username'] ?? '') !== '' ? $data['username'] : 'admin',
            'password' => $password,
        ]);

        return $this->redirect('/install/website');
    }

    // --- Step 4 ------------------------------------------------------

    public function website(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }
        if (!Session::has('install.admin')) {
            return $this->redirect('/install/admin');
        }

        return $this->installView('install.website', 4, [
            'data' => Session::get('install.website', [
                'site_name' => 'ગણપતિ બાપા ક્વિઝ શો',
                'timezone'  => 'Asia/Kolkata',
                'language'  => 'gu',
                'currency'  => '₹',
                'demo_data' => '1',
            ]),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'languages' => ['gu' => 'ગુજરાતી (Gujarati)', 'hi' => 'हिन्दी (Hindi)', 'en' => 'English'],
        ]);
    }

    public function saveWebsite(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }

        $data = Validator::validate($request->all(), [
            'site_name' => 'required|string|max:150',
            'timezone'  => 'required|string|max:64',
            'language'  => 'required|in:gu,hi,en',
            'currency'  => 'required|string|max:8',
        ], ['site_name' => 'Website name']);

        if (!in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $this->error('Choose a valid timezone.');
            return $this->redirect('/install/website');
        }

        Session::put('install.website', [
            'site_name' => $data['site_name'],
            'timezone'  => $data['timezone'],
            'language'  => $data['language'],
            'currency'  => $data['currency'],
            'demo_data' => $request->bool('demo_data', false) ? '1' : '0',
            'site_url'  => $this->guessBaseUrl($request),
        ]);

        return $this->redirect('/install/run');
    }

    // --- Step 5 ------------------------------------------------------

    public function run(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }
        if (!Session::has('install.website')) {
            return $this->redirect('/install/website');
        }

        return $this->installView('install.run', 5, [
            'database' => Session::get('install.database'),
            'admin'    => Session::get('install.admin'),
            'website'  => Session::get('install.website'),
        ]);
    }

    public function runInstall(Request $request): Response
    {
        if ($this->isLocked()) {
            return $this->lockedResponse();
        }

        $database = Session::get('install.database');
        $admin    = Session::get('install.admin');
        $website  = Session::get('install.website');

        if (!is_array($database) || !is_array($admin) || !is_array($website)) {
            $this->error('The installation session expired. Please start again.');
            return $this->redirect('/install/requirements');
        }

        @set_time_limit(300);
        $steps = [];

        try {
            // 1. Write the .env file so nothing needs editing by hand.
            $appKey = Crypto::generateKey();
            $envPath = Application::instance()->rootPath('.env');
            $written = Env::write($envPath, [
                'APP_NAME'      => $website['site_name'],
                'APP_ENV'       => 'production',
                'APP_DEBUG'     => 'false',
                'APP_URL'       => $website['site_url'] ?? '',
                'APP_TIMEZONE'  => $website['timezone'],
                'APP_LOCALE'    => $website['language'],
                'APP_KEY'       => $appKey,
                'DB_DRIVER'     => 'mysql',
                'DB_HOST'       => $database['host'],
                'DB_PORT'       => $database['port'],
                'DB_DATABASE'   => $database['database'],
                'DB_USERNAME'   => $database['username'],
                'DB_PASSWORD'   => $database['password'],
                'SESSION_LIFETIME' => '7200',
            ]);
            if (!$written) {
                throw new RuntimeException('The .env file could not be written. Make the project folder writable and try again.');
            }
            $steps[] = 'Configuration file written';

            // Reload configuration with the new values.
            Config::load(Application::instance()->rootPath('config'));
            Database::swap(Database::fromConfig(array_merge($database, ['charset' => 'utf8mb4'])));

            // 2. Schema.
            $ran = MigrationService::make()->migrate();
            $steps[] = count($ran) . ' migration(s) executed';

            // 3. Core seed data.
            $seeder = SeederService::make();
            $seeder->seedCore();
            $steps[] = 'Default settings, prize ladder, lifelines and categories created';

            // 4. Website settings from the wizard.
            SettingsService::setMany([
                'site_name'       => $website['site_name'],
                'timezone'        => $website['timezone'],
                'language'        => $website['language'],
                'currency_symbol' => $website['currency'],
                'current_version' => (string) Config::get('app.version', '1.0.0'),
            ]);
            $steps[] = 'Website settings saved';

            // 5. Administrator account.
            $this->createAdministrator($admin);
            $steps[] = 'Administrator account created';

            // 6. Optional demo content.
            if (($website['demo_data'] ?? '0') === '1') {
                $seeder->seedDemo();
                $steps[] = 'Demo questions, gifts and participants added';
            }

            // 7. Lock the installer.
            $lockFile = Application::instance()->storagePath('installed.lock');
            $lockWritten = @file_put_contents($lockFile, (string) json_encode([
                'installed_at' => date('c'),
                'version'      => (string) Config::get('app.version', '1.0.0'),
                'admin_email'  => $admin['email'],
            ], JSON_PRETTY_PRINT));

            if ($lockWritten === false) {
                throw new RuntimeException('Installation finished but storage/installed.lock could not be written. Make the storage folder writable.');
            }
            @chmod($lockFile, 0640);
            Application::instance()->markInstalled();
            $steps[] = 'Installer locked';

            Session::put('install.result', [
                'steps'       => $steps,
                'admin_email' => $admin['email'],
                'site_name'   => $website['site_name'],
            ]);
            Session::forget('install.database');
            Session::forget('install.admin');
            Session::forget('install.website');

            return $this->redirect('/install/complete');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Installation failed: ' . $e->getMessage());
            $this->error('Installation failed: ' . $e->getMessage());
            Session::put('install.partial_steps', $steps);
            return $this->redirect('/install/run');
        }
    }

    // --- Step 6 ------------------------------------------------------

    public function complete(Request $request): Response
    {
        $result = Session::get('install.result');
        if (!is_array($result)) {
            if ($this->isLocked()) {
                return $this->lockedResponse();
            }
            return $this->redirect('/install/requirements');
        }
        Session::forget('install.result');

        return $this->installView('install.complete', 6, ['result' => $result]);
    }

    // -----------------------------------------------------------------

    private function isLocked(): bool
    {
        return is_file(Application::instance()->storagePath('installed.lock'));
    }

    private function lockedResponse(): Response
    {
        return $this->view('install.locked', [], 403);
    }

    /** @param array<string,mixed> $data */
    private function installView(string $template, int $step, array $data = []): Response
    {
        return $this->view($template, array_merge($data, [
            'steps'       => self::STEPS,
            'currentStep' => $step,
            'appVersion'  => (string) Config::get('app.version', '1.0.0'),
        ]));
    }

    /**
     * @param array<string,mixed> $config
     */
    private function testConnection(array $config, bool $createIfMissing): void
    {
        $dsnBase = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']);

        try {
            $server = new PDO($dsnBase, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
            ]);
        } catch (\PDOException $e) {
            throw new RuntimeException('Could not connect to the database server: ' . $this->friendlyPdoMessage($e));
        }

        $exists = (bool) $server->query(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $server->quote((string) $config['database'])
        )->fetchColumn();

        if (!$exists) {
            if (!$createIfMissing) {
                throw new RuntimeException(
                    'The database "' . $config['database'] . '" does not exist. '
                    . 'Create it in your hosting panel, or tick "Create the database if it does not exist".'
                );
            }
            $name = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $config['database']);
            try {
                $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            } catch (\PDOException $e) {
                throw new RuntimeException('The database could not be created: ' . $this->friendlyPdoMessage($e));
            }
        }

        // Confirm we can actually use it and create tables in it.
        try {
            $pdo = new PDO(
                $dsnBase . ';dbname=' . $config['database'],
                (string) $config['username'],
                (string) $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec('CREATE TABLE IF NOT EXISTS _install_probe (id INT PRIMARY KEY) ENGINE=InnoDB');
            $pdo->exec('DROP TABLE IF EXISTS _install_probe');
        } catch (\PDOException $e) {
            throw new RuntimeException(
                'Connected, but this user cannot create tables in "' . $config['database'] . '": ' . $this->friendlyPdoMessage($e)
            );
        }
    }

    private function friendlyPdoMessage(\PDOException $e): string
    {
        $message = $e->getMessage();
        if (str_contains($message, 'Access denied')) {
            return 'the username or password was rejected.';
        }
        if (str_contains($message, 'Unknown database')) {
            return 'that database does not exist.';
        }
        if (str_contains($message, 'Connection refused') || str_contains($message, 'getaddrinfo')) {
            return 'the host or port is not reachable.';
        }
        return $message;
    }

    /** @param array<string,mixed> $admin */
    private function createAdministrator(array $admin): void
    {
        $db = Database::instance();
        $roleId = (int) ($db->scalar("SELECT id FROM roles WHERE slug = 'admin'") ?? 0);
        if ($roleId === 0) {
            throw new RuntimeException('The administrator role is missing - seeding did not complete.');
        }

        $existing = $db->selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$admin['email']]);
        $now = date('Y-m-d H:i:s');

        $payload = [
            'role_id'       => $roleId,
            'name'          => (string) $admin['name'],
            'email'         => (string) $admin['email'],
            'username'      => (string) $admin['username'],
            'password_hash' => AuthService::hashPassword((string) $admin['password']),
            'status'        => 'active',
            'updated_at'    => $now,
        ];

        if ($existing === null) {
            $payload['created_at'] = $now;
            $db->insert('users', $payload);
        } else {
            $db->update('users', $payload, ['id' => (int) $existing['id']]);
        }
    }

    private function guessBaseUrl(Request $request): string
    {
        $scheme = $request->isSecure() ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $base = Application::basePath();
        return $scheme . '://' . $host . $base;
    }
}
