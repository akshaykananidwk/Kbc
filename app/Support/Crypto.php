<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Env;
use RuntimeException;

/**
 * Authenticated symmetric encryption used for secrets kept in the database
 * (currently the GitHub token). Key material lives in .env only.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1:';

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    private static function key(): string
    {
        $raw = (string) (Env::get('APP_KEY', '') ?? '');
        if ($raw === '') {
            throw new RuntimeException('APP_KEY is not set. Encryption is unavailable.');
        }
        if (str_starts_with($raw, 'base64:')) {
            $decoded = base64_decode(substr($raw, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('APP_KEY is not valid base64.');
            }
            $raw = $decoded;
        }
        if (strlen($raw) < 32) {
            $raw = hash('sha256', $raw, true);
        }
        return substr($raw, 0, 32);
    }

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            // Value was stored before encryption was configured.
            return $payload;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
