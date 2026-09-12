<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

/**
 * Session based authentication with per-account and per-IP rate limiting.
 */
final class AuthService
{
    private const SESSION_KEY = '_auth_user_id';
    /** @var array<string,mixed>|null */
    private static ?array $cachedUser = null;

    /**
     * @return array{success:bool,message:string,user:array<string,mixed>|null}
     */
    public static function attempt(string $identifier, string $password, string $ip, string $userAgent): array
    {
        $db = Database::instance();
        $limit = max(1, SettingsService::int('login_attempt_limit', 5));
        $lockoutMinutes = max(1, SettingsService::int('lockout_minutes', 15));

        // Per-IP throttle so an attacker cannot spray many accounts.
        $ipAttempts = (int) ($db->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND successful = 0 AND attempted_at > ?',
            [$ip, date('Y-m-d H:i:s', time() - $lockoutMinutes * 60)]
        ) ?? 0);

        if ($ipAttempts >= $limit * 3) {
            self::recordAttempt($identifier, $ip, false, $userAgent);
            return [
                'success' => false,
                'message' => 'Too many failed attempts from this device. Try again in ' . $lockoutMinutes . ' minutes.',
                'user'    => null,
            ];
        }

        $user = $db->selectOne(
            'SELECT u.*, r.slug AS role_slug, r.level AS role_level
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE (u.email = :id OR u.username = :id) LIMIT 1',
            ['id' => $identifier]
        );

        // Constant-ish work whether or not the account exists.
        $hash = (string) ($user['password_hash'] ?? '$2y$12$' . str_repeat('a', 53));
        $passwordOk = password_verify($password, $hash);

        if ($user === null) {
            self::recordAttempt($identifier, $ip, false, $userAgent);
            return ['success' => false, 'message' => 'Invalid credentials.', 'user' => null];
        }

        if ((string) $user['status'] !== 'active') {
            self::recordAttempt($identifier, $ip, false, $userAgent);
            return ['success' => false, 'message' => 'This account is not active. Contact an administrator.', 'user' => null];
        }

        $lockedUntil = $user['locked_until'] ?? null;
        if ($lockedUntil !== null && strtotime((string) $lockedUntil) > time()) {
            $minutes = (int) ceil((strtotime((string) $lockedUntil) - time()) / 60);
            self::recordAttempt($identifier, $ip, false, $userAgent);
            return [
                'success' => false,
                'message' => 'This account is locked. Try again in ' . max(1, $minutes) . ' minute(s).',
                'user'    => null,
            ];
        }

        if (!$passwordOk) {
            $failed = (int) $user['failed_attempts'] + 1;
            $update = ['failed_attempts' => $failed, 'updated_at' => date('Y-m-d H:i:s')];
            $message = 'Invalid credentials.';

            if ($failed >= $limit) {
                $update['locked_until'] = date('Y-m-d H:i:s', time() + $lockoutMinutes * 60);
                $update['failed_attempts'] = 0;
                $message = 'Too many failed attempts. This account is locked for ' . $lockoutMinutes . ' minutes.';
            }

            $db->update('users', $update, ['id' => (int) $user['id']]);
            self::recordAttempt($identifier, $ip, false, $userAgent);
            AuditService::log('login.failed', 'Failed sign-in for ' . $identifier);

            return ['success' => false, 'message' => $message, 'user' => null];
        }

        // Success - rotate the session id and clear the counters.
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put('_auth_role', (string) $user['role_slug']);
        Session::put('_auth_level', (int) $user['role_level']);
        Session::put('_created_at', time());
        self::$cachedUser = null;

        $db->update('users', [
            'failed_attempts' => 0,
            'locked_until'    => null,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $ip,
            'updated_at'      => date('Y-m-d H:i:s'),
        ], ['id' => (int) $user['id']]);

        self::recordAttempt($identifier, $ip, true, $userAgent);
        AuditService::log('login', 'Signed in successfully.');

        unset($user['password_hash']);
        return ['success' => true, 'message' => 'Welcome back, ' . $user['name'] . '!', 'user' => $user];
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditService::log('logout', 'Signed out.');
        }
        self::$cachedUser = null;
        Session::forget(self::SESSION_KEY);
        Session::forget('_auth_role');
        Session::forget('_auth_level');
        Session::destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }
        try {
            $user = Database::instance()->selectOne(
                'SELECT u.id, u.name, u.email, u.username, u.role_id, u.status, u.avatar, u.last_login_at,
                        r.slug AS role_slug, r.name AS role_name, r.level AS role_level
                 FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.id = ? LIMIT 1',
                [(int) $id]
            );
        } catch (\Throwable) {
            return null;
        }

        if ($user === null || (string) $user['status'] !== 'active') {
            return null;
        }
        self::$cachedUser = $user;
        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function hasRole(string $roleSlug): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if ((string) $user['role_slug'] === $roleSlug) {
            return true;
        }
        // Higher privilege levels satisfy lower requirements.
        $required = (int) (Database::instance()->scalar('SELECT level FROM roles WHERE slug = ?', [$roleSlug]) ?? 100);
        return (int) $user['role_level'] >= $required;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        $count = (int) (Database::instance()->scalar(
            'SELECT COUNT(*) FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ? AND p.slug = ?',
            [(int) $user['role_id'], $permission]
        ) ?? 0);
        return $count > 0;
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** @return array{ok:bool,message:string} */
    public static function passwordPolicy(string $password): array
    {
        if (strlen($password) < 8) {
            return ['ok' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return ['ok' => false, 'message' => 'Password must contain at least one letter and one number.'];
        }
        return ['ok' => true, 'message' => ''];
    }

    private static function recordAttempt(string $identifier, string $ip, bool $successful, string $userAgent): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier'   => substr($identifier, 0, 190),
                'ip_address'   => $ip,
                'successful'   => $successful ? 1 : 0,
                'user_agent'   => substr($userAgent, 0, 255),
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
            // Keep the table small.
            if (random_int(1, 50) === 1) {
                Database::instance()->run(
                    'DELETE FROM login_attempts WHERE attempted_at < ?',
                    [date('Y-m-d H:i:s', time() - 30 * 86400)]
                );
            }
        } catch (\Throwable) {
            // Never block a sign-in because the audit insert failed.
        }
    }
}
