<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal QR code generator producing an SVG.
 *
 * Written in-house so the application keeps its promise of no Composer and
 * no external CDN: a phone has to be able to scan the audience-poll code
 * from a TV screen on a venue with no internet.
 *
 * Supports byte mode with error-correction level M, versions 1-10, which
 * comfortably covers the short URLs this application produces.
 */
final class QrCode
{
    private const PAD = [0xEC, 0x11];

    /** Data codeword capacity for byte mode, EC level M, versions 1..10. */
    private const CAPACITY = [
        1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86,
        6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
    ];

    /** Total codewords per version (data + error correction). */
    private const TOTAL_CODEWORDS = [
        1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134,
        6 => 172, 7 => 196, 8 => 242, 9 => 292, 10 => 346,
    ];

    /**
     * EC blocks for level M: [number of blocks, data codewords per block].
     * Versions with two block groups list both.
     */
    private const EC_BLOCKS = [
        1  => [[1, 16]],
        2  => [[1, 28]],
        3  => [[1, 44]],
        4  => [[2, 32]],
        5  => [[2, 43]],
        6  => [[4, 27]],
        7  => [[4, 31]],
        8  => [[2, 38], [2, 39]],
        9  => [[3, 36], [2, 37]],
        10 => [[4, 43], [1, 44]],
    ];

    /** Alignment pattern centres per version. */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var array<int,int> */
    private static array $expTable = [];
    /** @var array<int,int> */
    private static array $logTable = [];

    private int $version;
    private int $size;
    /** @var array<int,array<int,int|null>> */
    private array $grid = [];
    /** @var array<int,array<int,bool>> */
    private array $reserved = [];

    private function __construct(private string $data)
    {
        $this->version = $this->pickVersion(strlen($data));
        $this->size = 17 + 4 * $this->version;
        $this->build();
    }

    /**
     * Render a QR code for the given text as an SVG string.
     *
     * @param int $scale Pixel size of one module in the SVG viewBox.
     */
    public static function svg(string $text, int $scale = 8, int $quietZone = 4, string $dark = '#000000', string $light = '#ffffff'): string
    {
        $qr = new self($text);
        return $qr->toSvg($scale, $quietZone, $dark, $light);
    }

    /** @return array<int,array<int,int>> The finished module matrix. */
    public static function matrix(string $text): array
    {
        $qr = new self($text);
        $out = [];
        foreach ($qr->grid as $y => $row) {
            foreach ($row as $x => $value) {
                $out[$y][$x] = (int) $value;
            }
        }
        return $out;
    }

    private function pickVersion(int $length): int
    {
        foreach (self::CAPACITY as $version => $capacity) {
            // 4 bits mode + 8 or 16 bits length + payload
            $header = $version < 10 ? 2 : 3;
            if ($length + $header <= $capacity) {
                return $version;
            }
        }
        throw new RuntimeException('The text is too long for a version 10 QR code.');
    }

    private function build(): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $this->grid[$y][$x] = null;
                $this->reserved[$y][$x] = false;
            }
        }

        $this->placeFinder(0, 0);
        $this->placeFinder($this->size - 7, 0);
        $this->placeFinder(0, $this->size - 7);
        $this->placeSeparators();
        $this->placeAlignment();
        $this->placeTiming();
        $this->reserveFormat();

        // Dark module, always set.
        $this->set(8, $this->size - 8, 1, true);

        $this->placeData($this->encode());

        $mask = $this->chooseMask();
        $this->applyMask($mask);
        $this->writeFormat($mask);
    }

    // ---------------------------------------------------------------
    // Function patterns
    // ---------------------------------------------------------------

    private function placeFinder(int $left, int $top): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $px = $left + $x;
                $py = $top + $y;
                if ($px < 0 || $py < 0 || $px >= $this->size || $py >= $this->size) {
                    continue;
                }
                $inRing = ($x >= 0 && $x <= 6 && ($y === 0 || $y === 6))
                    || ($y >= 0 && $y <= 6 && ($x === 0 || $x === 6));
                $inCore = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                $this->set($px, $py, ($inRing || $inCore) ? 1 : 0, true);
            }
        }
    }

    private function placeSeparators(): void
    {
        // Handled by the -1..7 sweep in placeFinder, which writes the white ring.
    }

    private function placeAlignment(): void
    {
        $centres = self::ALIGNMENT[$this->version];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                // Skip the three finder corners.
                if (($cx <= 8 && $cy <= 8)
                    || ($cx <= 8 && $cy >= $this->size - 9)
                    || ($cx >= $this->size - 9 && $cy <= 8)) {
                    continue;
                }
                for ($y = -2; $y <= 2; $y++) {
                    for ($x = -2; $x <= 2; $x++) {
                        $onEdge = abs($x) === 2 || abs($y) === 2;
                        $isCentre = $x === 0 && $y === 0;
                        $this->set($cx + $x, $cy + $y, ($onEdge || $isCentre) ? 1 : 0, true);
                    }
                }
            }
        }
    }

    private function placeTiming(): void
    {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $bit = $i % 2 === 0 ? 1 : 0;
            $this->set($i, 6, $bit, true);
            $this->set(6, $i, $bit, true);
        }
    }

    private function reserveFormat(): void
    {
        for ($i = 0; $i < 9; $i++) {
            if (!$this->reserved[$i][8]) { $this->reserved[$i][8] = true; $this->grid[$i][8] = 0; }
            if (!$this->reserved[8][$i]) { $this->reserved[8][$i] = true; $this->grid[8][$i] = 0; }
        }
        for ($i = 0; $i < 8; $i++) {
            $this->reserved[8][$this->size - 1 - $i] = true;
            $this->grid[8][$this->size - 1 - $i] = 0;
            $this->reserved[$this->size - 1 - $i][8] = true;
            $this->grid[$this->size - 1 - $i][8] = 0;
        }
    }

    private function set(int $x, int $y, int $value, bool $reserve = false): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            return;
        }
        $this->grid[$y][$x] = $value;
        if ($reserve) {
            $this->reserved[$y][$x] = true;
        }
    }

    // ---------------------------------------------------------------
    // Encoding
    // ---------------------------------------------------------------

    /** @return array<int,int> Final codeword stream, interleaved. */
    private function encode(): array
    {
        $bits = '';
        $bits .= '0100'; // byte mode
        $lengthBits = $this->version < 10 ? 8 : 16;
        $bits .= str_pad(decbin(strlen($this->data)), $lengthBits, '0', STR_PAD_LEFT);

        foreach (str_split($this->data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::CAPACITY[$this->version] * 8;
        $bits .= str_repeat('0', min(4, max(0, $capacityBits - strlen($bits))));
        while (strlen($bits) % 8 !== 0) {
            $bits .= '0';
        }

        $codewords = [];
        foreach (str_split($bits, 8) as $byte) {
            $codewords[] = bindec($byte);
        }
        $padIndex = 0;
        while (count($codewords) < self::CAPACITY[$this->version]) {
            $codewords[] = self::PAD[$padIndex % 2];
            $padIndex++;
        }

        return $this->interleave($codewords);
    }

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private function interleave(array $data): array
    {
        $totalCodewords = self::TOTAL_CODEWORDS[$this->version];
        $dataCount = self::CAPACITY[$this->version];

        $blockSpec = [];
        foreach (self::EC_BLOCKS[$this->version] as [$count, $size]) {
            for ($i = 0; $i < $count; $i++) {
                $blockSpec[] = $size;
            }
        }
        $ecLength = intdiv($totalCodewords - $dataCount, count($blockSpec));

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($blockSpec as $size) {
            $block = array_slice($data, $offset, $size);
            $offset += $size;
            $dataBlocks[] = $block;
            $ecBlocks[] = $this->reedSolomon($block, $ecLength);
        }

        $out = [];
        $maxData = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) { $out[] = $block[$i]; }
            }
        }
        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) { $out[] = $block[$i]; }
            }
        }

        return $out;
    }

    /**
     * @param array<int,int> $data
     * @return array<int,int>
     */
    private function reedSolomon(array $data, int $ecLength): array
    {
        self::initTables();

        $generator = [1];
        for ($i = 0; $i < $ecLength; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::mul($coefficient, self::$expTable[$i]);
            }
            $generator = $next;
        }

        $remainder = array_merge($data, array_fill(0, $ecLength, 0));
        for ($i = 0; $i < count($data); $i++) {
            $factor = $remainder[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($generator as $index => $coefficient) {
                $remainder[$i + $index] ^= self::mul($coefficient, $factor);
            }
        }

        return array_slice($remainder, count($data), $ecLength);
    }

    private static function initTables(): void
    {
        if (self::$expTable !== []) {
            return;
        }
        $value = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$expTable[$i] = $value;
            self::$logTable[$value] = $i;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /** @param array<int,int> $codewords */
    private function placeData(array $codewords): void
    {
        $bits = '';
        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $index = 0;
        $upward = true;
        for ($right = $this->size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right = 5; // the vertical timing column is skipped
            }
            for ($step = 0; $step < $this->size; $step++) {
                $y = $upward ? $this->size - 1 - $step : $step;
                for ($col = 0; $col < 2; $col++) {
                    $x = $right - $col;
                    if ($this->reserved[$y][$x]) {
                        continue;
                    }
                    $this->grid[$y][$x] = $index < strlen($bits) ? (int) $bits[$index] : 0;
                    $index++;
                }
            }
            $upward = !$upward;
        }
    }

    // ---------------------------------------------------------------
    // Masking
    // ---------------------------------------------------------------

    private function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($y + $x) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($y + $x) % 3 === 0,
            4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
            5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
            6 => ((($y * $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            7 => ((($y + $x) % 2) + (($y * $x) % 3)) % 2 === 0,
            default => false,
        };
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                if ($this->maskBit($mask, $x, $y)) {
                    $this->grid[$y][$x] ^= 1;
                }
            }
        }
    }

    /**
     * Try each mask and keep the one with the lowest penalty. A simplified
     * penalty (runs plus dark-ratio) is enough for reliable scanning.
     */
    private function chooseMask(): int
    {
        $best = 0;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $score = $this->penalty();
            $this->applyMask($mask); // XOR again to undo
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $mask;
            }
        }
        return $best;
    }

    private function penalty(): int
    {
        $score = 0;
        $dark = 0;

        for ($y = 0; $y < $this->size; $y++) {
            $runValue = -1;
            $runLength = 0;
            for ($x = 0; $x < $this->size; $x++) {
                $value = (int) $this->grid[$y][$x];
                $dark += $value;
                if ($value === $runValue) {
                    $runLength++;
                    if ($runLength === 5) { $score += 3; } elseif ($runLength > 5) { $score++; }
                } else {
                    $runValue = $value;
                    $runLength = 1;
                }
            }
        }

        for ($x = 0; $x < $this->size; $x++) {
            $runValue = -1;
            $runLength = 0;
            for ($y = 0; $y < $this->size; $y++) {
                $value = (int) $this->grid[$y][$x];
                if ($value === $runValue) {
                    $runLength++;
                    if ($runLength === 5) { $score += 3; } elseif ($runLength > 5) { $score++; }
                } else {
                    $runValue = $value;
                    $runLength = 1;
                }
            }
        }

        // 2x2 blocks of one colour
        for ($y = 0; $y < $this->size - 1; $y++) {
            for ($x = 0; $x < $this->size - 1; $x++) {
                $v = $this->grid[$y][$x];
                if ($v === $this->grid[$y][$x + 1] && $v === $this->grid[$y + 1][$x] && $v === $this->grid[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        $total = $this->size * $this->size;
        $percent = (int) round(($dark / $total) * 100);
        $score += (int) (abs($percent - 50) / 5) * 10;

        return $score;
    }

    private function writeFormat(int $mask): void
    {
        // EC level M is 00 in the format bits.
        $format = (0b00 << 3) | $mask;
        $value = $format << 10;
        for ($i = 4; $i >= 0; $i--) {
            if ($value & (1 << ($i + 10))) {
                $value ^= 0b10100110111 << $i;
            }
        }
        $bits = (($format << 10) | $value) ^ 0b101010000010010;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;

            // Around the top-left finder
            if ($i < 6) {
                $this->grid[$i][8] = $bit;
            } elseif ($i === 6) {
                $this->grid[7][8] = $bit;
            } elseif ($i === 7) {
                $this->grid[8][8] = $bit;
            } elseif ($i === 8) {
                $this->grid[8][7] = $bit;
            } else {
                $this->grid[8][14 - $i] = $bit;
            }

            // Mirrored copy
            if ($i < 8) {
                $this->grid[8][$this->size - 1 - $i] = $bit;
            } else {
                $this->grid[$this->size - 15 + $i][8] = $bit;
            }
        }
    }

    // ---------------------------------------------------------------
    // Output
    // ---------------------------------------------------------------

    private function toSvg(int $scale, int $quietZone, string $dark, string $light): string
    {
        $modules = $this->size + $quietZone * 2;
        $dimension = $modules * $scale;

        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ((int) $this->grid[$y][$x] === 1) {
                    $px = ($x + $quietZone) * $scale;
                    $py = ($y + $quietZone) * $scale;
                    $path .= 'M' . $px . ',' . $py . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dimension . '" height="' . $dimension . '" '
            . 'viewBox="0 0 ' . $dimension . ' ' . $dimension . '" shape-rendering="crispEdges" role="img" aria-label="QR code">'
            . '<rect width="' . $dimension . '" height="' . $dimension . '" fill="' . htmlspecialchars($light, ENT_QUOTES) . '"/>'
            . '<path d="' . $path . '" fill="' . htmlspecialchars($dark, ENT_QUOTES) . '"/>'
            . '</svg>';
    }
}
