<?php
declare(strict_types=1);

namespace App\Support;

/**
 * CSV helpers with explicit parameters.
 *
 * PHP 8.4 deprecates calling fputcsv()/fgetcsv() without the $escape
 * argument, and an empty escape string is the correct, standards-compliant
 * behaviour for spreadsheet interoperability anyway.
 */
final class Csv
{
    /**
     * @param resource $handle
     * @param array<int,mixed> $row
     */
    public static function put($handle, array $row): void
    {
        $clean = [];
        foreach ($row as $value) {
            $value = $value === null ? '' : (string) $value;
            // Neutralise spreadsheet formula injection (=, +, -, @ prefixes).
            if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                $value = "'" . $value;
            }
            $clean[] = $value;
        }
        fputcsv($handle, $clean, ',', '"', '');
    }

    /**
     * @param resource $handle
     * @return array<int,string>|false
     */
    public static function get($handle): array|false
    {
        return fgetcsv($handle, 0, ',', '"', '');
    }

    /** Writes the UTF-8 BOM so Excel opens Gujarati/Hindi text correctly. */
    public static function bom($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
    }
}
