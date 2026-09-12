<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    public static function rotate(): string
    {
        Session::forget(self::KEY);
        return self::token();
    }

    public static function check(Request $request): bool
    {
        $expected = Session::get(self::KEY);
        if (!is_string($expected) || $expected === '') {
            return false;
        }
        $provided = $request->input('_token');
        if (!is_string($provided) || $provided === '') {
            $provided = $request->header('X-CSRF-Token') ?? '';
        }
        return is_string($provided) && $provided !== '' && hash_equals($expected, $provided);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
