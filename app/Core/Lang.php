<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Interface translations.
 *
 * Question and participant content is already stored in whatever language
 * it was typed in; this covers the buttons and labels around it, so an
 * operator who reads only Gujarati can run the show.
 *
 * Any key with no translation falls back to the key itself, which is
 * written in English - so a missing entry degrades to readable English
 * rather than to a blank or a debug token.
 */
final class Lang
{
    private static string $locale = 'en';
    /** @var array<string,array<string,string>> */
    private static array $loaded = [];
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    public static function use(string $locale): void
    {
        $locale = preg_replace('/[^a-z_-]/i', '', $locale) ?: 'en';
        self::$locale = $locale;
        self::load($locale);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /** @return array<int,string> */
    public static function available(): array
    {
        if (self::$path === '' || !is_dir(self::$path)) {
            return ['en'];
        }
        $locales = [];
        foreach (glob(self::$path . '/*.php') ?: [] as $file) {
            $locales[] = basename($file, '.php');
        }
        return $locales === [] ? ['en'] : $locales;
    }

    private static function load(string $locale): void
    {
        if (isset(self::$loaded[$locale]) || self::$path === '') {
            return;
        }
        $file = self::$path . '/' . $locale . '.php';
        $data = is_file($file) ? require $file : [];
        self::$loaded[$locale] = is_array($data) ? $data : [];
    }

    /**
     * Translate a key.
     *
     * @param array<string,string|int> $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        self::load(self::$locale);
        $text = self::$loaded[self::$locale][$key] ?? $key;

        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }
        return $text;
    }

    public static function has(string $key): bool
    {
        self::load(self::$locale);
        return isset(self::$loaded[self::$locale][$key]);
    }

    /** How complete a translation is, for the settings screen. */
    public static function coverage(string $locale): array
    {
        self::load($locale);
        self::load('gu');
        $reference = array_keys(self::$loaded['gu'] ?? []);
        $target = self::$loaded[$locale] ?? [];
        $translated = 0;
        foreach ($reference as $key) {
            if (isset($target[$key]) && trim($target[$key]) !== '') {
                $translated++;
            }
        }
        return [
            'total'      => count($reference),
            'translated' => $locale === 'en' ? count($reference) : $translated,
        ];
    }
}
