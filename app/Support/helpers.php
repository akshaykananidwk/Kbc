<?php
declare(strict_types=1);

/**
 * A handful of globals so the templates stay readable. Everything that
 * reaches HTML goes through e() unless explicitly marked raw.
 */

use App\Core\Application;
use App\Services\SettingsService;

if (!function_exists('e')) {
    /** Escape a value for HTML output. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /**
     * Translate an interface string.
     *
     * The key is the English text, so an untranslated string still reads
     * correctly rather than showing a debug token.
     *
     * @param array<string,string|int> $replace
     */
    function __(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}

if (!function_exists('_e')) {
    /** Translate and escape in one step, for use inside templates. */
    function _e(string $key, array $replace = []): string
    {
        return e(\App\Core\Lang::get($key, $replace));
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Application::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Application::asset($path);
    }
}

if (!function_exists('upload_url')) {
    function upload_url(?string $relative): string
    {
        return Application::uploadUrl($relative);
    }
}

if (!function_exists('money')) {
    function money(float|int|string|null $amount): string
    {
        return SettingsService::money((float) ($amount ?? 0));
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return SettingsService::get($key, $default);
    }
}

if (!function_exists('old_value')) {
    /** @param array<string,mixed> $old */
    function old_value(array $old, string $key, mixed $fallback = ''): string
    {
        return (string) ($old[$key] ?? $fallback ?? '');
    }
}

if (!function_exists('active_when')) {
    /** Marks the current nav item. */
    function active_when(string $path, bool $exact = false): string
    {
        $current = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $base = Application::basePath();
        if ($base !== '' && str_starts_with($current, $base)) {
            $current = substr($current, strlen($base));
        }
        $current = '/' . trim($current, '/');
        $path = '/' . trim($path, '/');

        if ($exact) {
            return $current === $path ? 'is-active' : '';
        }
        return $current === $path || str_starts_with($current, $path . '/') ? 'is-active' : '';
    }
}

if (!function_exists('datetime_label')) {
    function datetime_label(?string $value, string $format = 'd M Y, H:i'): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        $time = strtotime($value);
        return $time === false ? '-' : date($format, $time);
    }
}

if (!function_exists('duration_label')) {
    function duration_label(int|float|null $seconds): string
    {
        $seconds = (int) ($seconds ?? 0);
        if ($seconds <= 0) {
            return '-';
        }
        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;
        if ($minutes >= 60) {
            return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
        }
        return $minutes > 0 ? $minutes . 'm ' . $remaining . 's' : $remaining . 's';
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'active' => 'ok', 'completed' => 'ok', 'running' => 'info', 'pending' => 'warn',
            'paused' => 'warn', 'inactive' => 'muted', 'locked' => 'bad', 'wrong_answer' => 'bad',
            'time_up' => 'bad', 'quit' => 'warn', 'abandoned' => 'muted', 'failed' => 'bad',
            'rolled_back' => 'warn', 'out_of_stock' => 'muted', 'played' => 'info',
        ];
        return $map[$status] ?? 'muted';
    }
}
