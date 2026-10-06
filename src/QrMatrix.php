<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * The finished QR symbol: a square grid of dark/light modules (without quiet zone).
 */
final class QrMatrix
{
    public readonly int $size;

    /**
     * @param list<list<bool>> $modules rows indexed [y][x], true = dark
     */
    public function __construct(
        public readonly int $version,
        public readonly ErrorCorrectionLevel $errorCorrection,
        public readonly int $mask,
        private readonly array $modules,
    ) {
        if ($version < Encoder::MIN_VERSION || $version > Encoder::MAX_VERSION) {
            throw new QrException('QR version must be between 1 and 40.');
        }
        if ($mask < 0 || $mask > 7) {
            throw new QrException('Mask must be between 0 and 7.');
        }
        $size = $version * 4 + 17;
        if (count($modules) !== $size) {
            throw new QrException(sprintf('A version %d matrix must have %d rows.', $version, $size));
        }
        $this->size = $size;
    }

    public function isDark(int $x, int $y): bool
    {
        if ($x < 0 || $y < 0 || $x >= $this->size || $y >= $this->size) {
            throw new QrException(sprintf('Module (%d, %d) is outside the %dx%d matrix.', $x, $y, $this->size, $this->size));
        }

        return $this->modules[$y][$x];
    }

    /** @return list<list<bool>> rows indexed [y][x] */
    public function toArray(): array
    {
        return $this->modules;
    }

    /** Plain-text rendering, handy for CLI debugging. */
    public function toText(string $dark = '##', string $light = '  '): string
    {
        $out = '';
        foreach ($this->modules as $row) {
            foreach ($row as $module) {
                $out .= $module ? $dark : $light;
            }
            $out .= "\n";
        }

        return $out;
    }
}
