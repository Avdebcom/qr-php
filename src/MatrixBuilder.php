<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Places function patterns and codewords into the module grid and applies the mask.
 *
 * @internal used by Encoder
 */
final class MatrixBuilder
{
    private readonly int $size;
    /** @var list<list<bool>> */
    private array $modules;
    /** @var list<list<bool>> */
    private array $isFunction;

    private function __construct(
        private readonly int $version,
        private readonly ErrorCorrectionLevel $ecl,
    ) {
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = $this->modules;
    }

    /** @param list<int> $codewords interleaved data + ECC codewords */
    public static function build(int $version, ErrorCorrectionLevel $ecl, array $codewords, ?int $mask = null): QrMatrix
    {
        if (count($codewords) !== intdiv(Encoder::rawDataModules($version), 8)) {
            throw new QrException('Codeword count does not match the QR version.');
        }
        $builder = new self($version, $ecl);
        $builder->drawFunctionPatterns();
        $builder->drawCodewords($codewords);

        if ($mask === null) {
            $mask = 0;
            $best = PHP_INT_MAX;
            for ($candidate = 0; $candidate < 8; $candidate++) {
                $builder->applyMask($candidate);
                $builder->drawFormatBits($candidate);
                $penalty = $builder->penalty();
                if ($penalty < $best) {
                    $best = $penalty;
                    $mask = $candidate;
                }
                $builder->applyMask($candidate); // XOR again to undo
            }
        }
        $builder->applyMask($mask);
        $builder->drawFormatBits($mask);

        return new QrMatrix($version, $ecl, $mask, $builder->modules);
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $positions = $this->alignmentPositions();
        $last = count($positions) - 1;
        foreach ($positions as $i => $px) {
            foreach ($positions as $j => $py) {
                $overlapsFinder = ($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0);
                if (!$overlapsFinder) {
                    $this->drawAlignment($px, $py);
                }
            }
        }
        $this->drawFormatBits(0); // reserve the area; overwritten once the mask is known
        $this->drawVersionBits();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $distance = max(abs($dx), abs($dy));
                    $this->setFunction($x, $y, $distance !== 2 && $distance !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return list<int> */
    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $count = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $count * 3 + 5, $count * 4 - 4) * 2;
        $positions = [];
        for ($i = $count - 1, $pos = $this->size - 7; $i >= 1; $i--, $pos -= $step) {
            $positions[$i] = $pos;
        }
        $positions[0] = 6;
        ksort($positions);

        return array_values($positions);
    }

    private function drawFormatBits(int $mask): void
    {
        $data = ($this->ecl->formatBits() << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $this->size - 8, true); // the always-dark module
    }

    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    /** @param list<int> $codewords */
    private function drawCodewords(array $codewords): void
    {
        $totalBits = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing column
            }
            $upward = (($right + 1) & 2) === 0;
            for ($vert = 0; $vert < $this->size; $vert++) {
                $y = $upward ? $this->size - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Mask penalty score per ISO/IEC 18004 section 7.8.3 (lower is better). */
    private function penalty(): int
    {
        $n = $this->size;
        $m = $this->modules;
        $score = 0;
        $dark = 0;
        for ($i = 0; $i < $n; $i++) {
            $row = '';
            $column = '';
            for ($j = 0; $j < $n; $j++) {
                $row .= $m[$i][$j] ? '1' : '0';
                $column .= $m[$j][$i] ? '1' : '0';
                if ($m[$i][$j]) {
                    $dark++;
                }
            }
            $score += self::linePenalty($row) + self::linePenalty($column);
        }
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                $c = $m[$y][$x];
                if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        $total = $n * $n;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;

        return $score + max(0, $k) * 10;
    }

    private static function linePenalty(string $line): int
    {
        $score = 0;
        $run = 1;
        $length = strlen($line);
        for ($i = 1; $i <= $length; $i++) {
            if ($i < $length && $line[$i] === $line[$i - 1]) {
                $run++;
                continue;
            }
            if ($run >= 5) {
                $score += $run - 2;
            }
            $run = 1;
        }

        return $score + 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
    }
}
