<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * QR error correction level. Higher levels survive more damage but hold less data.
 */
enum ErrorCorrectionLevel: string
{
    case Low = 'L';      // ~7 % of codewords can be restored
    case Medium = 'M';   // ~15 %
    case Quartile = 'Q'; // ~25 %
    case High = 'H';     // ~30 %

    public static function fromString(string $level): self
    {
        $parsed = self::tryFrom(strtoupper(trim($level)));
        if ($parsed === null) {
            throw new QrException(sprintf('Unknown error correction level "%s"; expected L, M, Q or H.', $level));
        }

        return $parsed;
    }

    /** The two-bit value used in the format information (ISO/IEC 18004 table 12). */
    public function formatBits(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 0,
            self::Quartile => 3,
            self::High => 2,
        };
    }
}
