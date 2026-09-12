<?php
declare(strict_types=1);

namespace App\Support;

final class Money
{
    /**
     * Format using the Indian grouping system (1,60,000) which is what the
     * prize ladder is displayed in, with a configurable currency symbol.
     */
    public static function format(float|int|string $amount, string $symbol = '₹', bool $indianGrouping = true): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        $amount = abs($amount);

        $hasPaise = fmod($amount, 1.0) !== 0.0;
        $decimals = $hasPaise ? 2 : 0;

        if ($indianGrouping) {
            $formatted = self::indianFormat($amount, $decimals);
        } else {
            $formatted = number_format($amount, $decimals, '.', ',');
        }

        return ($negative ? '-' : '') . $symbol . $formatted;
    }

    private static function indianFormat(float $amount, int $decimals): string
    {
        $fixed = number_format($amount, $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $fixed), 2, '');

        if (strlen($whole) <= 3) {
            $grouped = $whole;
        } else {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) ?? $rest;
            $grouped = $rest . ',' . $last3;
        }

        return $decimals > 0 ? $grouped . '.' . $fraction : $grouped;
    }

    /** Short label for TV display: 1,60,000 -> 1.6 Lakh */
    public static function shortLabel(float|int $amount, string $symbol = '₹'): string
    {
        $amount = (float) $amount;
        if ($amount >= 10000000) {
            return $symbol . self::trim($amount / 10000000) . ' Cr';
        }
        if ($amount >= 100000) {
            return $symbol . self::trim($amount / 100000) . ' Lakh';
        }
        if ($amount >= 1000) {
            return $symbol . self::trim($amount / 1000) . 'K';
        }
        return self::format($amount, $symbol);
    }

    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
