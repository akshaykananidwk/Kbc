<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(bool $secure = false): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        if (PHP_SAPI === 'cli') {
            self::$started = true;
            return;
        }

        $lifetime = (int) Config::get('app.session_lifetime', 7200);

        session_name((string) Config::get('app.session_name', 'GANPATI_QUIZ_SESSION'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => Application::basePath() === '' ? '/' : Application::basePath() . '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.cookie_httponly', '1');

        session_start();
        self::$started = true;

        // Idle timeout.
        $now = time();
        $last = (int) (self::get('_last_activity', 0));
        if ($last > 0 && ($now - $last) > $lifetime) {
            self::destroy();
            session_start();
        }
        self::put('_last_activity', $now);

        // Periodic id rotation limits fixation windows.
        $created = (int) (self::get('_created_at', 0));
        if ($created === 0) {
            self::put('_created_at', $now);
        } elseif (($now - $created) > 1800) {
            self::regenerate();
            self::put('_created_at', $now);
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (PHP_SAPI !== 'cli' && ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name() ?: 'PHPSESSID', '', [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }
            session_destroy();
        }
    }

    /** Flash a one-shot message for the next request. */
    public static function flash(string $type, string $message): void
    {
        $bag = self::get('_flash', []);
        if (!is_array($bag)) {
            $bag = [];
        }
        $bag[] = ['type' => $type, 'message' => $message];
        self::put('_flash', $bag);
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        $bag = self::get('_flash', []);
        self::forget('_flash');
        return is_array($bag) ? $bag : [];
    }

    /** @param array<string,string> $errors */
    public static function flashErrors(array $errors): void
    {
        self::put('_errors', $errors);
    }

    /** @return array<string,string> */
    public static function pullErrors(): array
    {
        $errors = self::get('_errors', []);
        self::forget('_errors');
        return is_array($errors) ? $errors : [];
    }

    /** @param array<string,mixed> $input */
    public static function flashInput(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['github_token']);
        self::put('_old', $input);
    }

    /** @return array<string,mixed> */
    public static function pullOld(): array
    {
        $old = self::get('_old', []);
        self::forget('_old');
        return is_array($old) ? $old : [];
    }
}
