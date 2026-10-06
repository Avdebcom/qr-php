<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Turns a byte string into a QR matrix (byte mode, versions 1-40, automatic version choice).
 */
final class Encoder
{
    public const MIN_VERSION = 1;
    public const MAX_VERSION = 40;

    private const MODE_BYTE = 0b0100;

    /** Error correction codewords per block, versions 1-40. */
    private const ECC_PER_BLOCK = [
        'L' => [7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Number of error correction blocks, versions 1-40. */
    private const BLOCKS = [
        'L' => [1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    /**
     * @param int|null $mask force a mask pattern 0-7, or null to pick the lowest-penalty one
     */
    public function encode(string $data, ErrorCorrectionLevel $ecl = ErrorCorrectionLevel::Medium, ?int $mask = null): QrMatrix
    {
        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw new QrException('Mask must be between 0 and 7.');
        }
        $length = strlen($data);
        $version = self::chooseVersion($length, $ecl);
        $capacity = self::dataCodewords($version, $ecl);

        $bits = [];
        self::appendBits($bits, self::MODE_BYTE, 4);
        self::appendBits($bits, $length, self::charCountBits($version));
        for ($i = 0; $i < $length; $i++) {
            self::appendBits($bits, ord($data[$i]), 8);
        }
        self::appendBits($bits, 0, min(4, $capacity * 8 - count($bits))); // terminator
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);           // byte align

        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $byte = 0;
            foreach ($chunk as $bit) {
                $byte = ($byte << 1) | $bit;
            }
            $codewords[] = $byte;
        }
        for ($pad = 0xEC; count($codewords) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }

        return MatrixBuilder::build($version, $ecl, self::addErrorCorrection($codewords, $version, $ecl), $mask);
    }

    /** Maximum number of bytes a given version and level can hold. */
    public static function maxBytes(int $version, ErrorCorrectionLevel $ecl): int
    {
        if ($version < self::MIN_VERSION || $version > self::MAX_VERSION) {
            throw new QrException('QR version must be between 1 and 40.');
        }

        return intdiv(self::dataCodewords($version, $ecl) * 8 - 4 - self::charCountBits($version), 8);
    }

    /** Number of modules available for data + ECC (excludes function patterns and format/version info). */
    public static function rawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    private static function chooseVersion(int $length, ErrorCorrectionLevel $ecl): int
    {
        for ($version = self::MIN_VERSION; $version <= self::MAX_VERSION; $version++) {
            if ($length <= self::maxBytes($version, $ecl)) {
                return $version;
            }
        }
        throw new QrException(sprintf(
            'Data is %d bytes; the maximum at error correction level %s is %d bytes.',
            $length,
            $ecl->value,
            self::maxBytes(self::MAX_VERSION, $ecl),
        ));
    }

    private static function dataCodewords(int $version, ErrorCorrectionLevel $ecl): int
    {
        return intdiv(self::rawDataModules($version), 8)
            - self::ECC_PER_BLOCK[$ecl->value][$version - 1] * self::BLOCKS[$ecl->value][$version - 1];
    }

    private static function charCountBits(int $version): int
    {
        return $version <= 9 ? 8 : 16;
    }

    /** @param list<int> $bits */
    private static function appendBits(array &$bits, int $value, int $count): void
    {
        for ($i = $count - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * Splits data into blocks, appends Reed-Solomon codewords and interleaves them.
     *
     * @param list<int> $codewords
     * @return list<int>
     */
    private static function addErrorCorrection(array $codewords, int $version, ErrorCorrectionLevel $ecl): array
    {
        $numBlocks = self::BLOCKS[$ecl->value][$version - 1];
        $eccLen = self::ECC_PER_BLOCK[$ecl->value][$version - 1];
        $raw = intdiv(self::rawDataModules($version), 8);
        $numShort = $numBlocks - $raw % $numBlocks;
        $shortLen = intdiv($raw, $numBlocks);
        $divisor = ReedSolomon::divisor($eccLen);

        $blocks = [];
        $offset = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $dataLen = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $data = array_slice($codewords, $offset, $dataLen);
            $offset += $dataLen;
            $ecc = ReedSolomon::remainder($data, $divisor);
            if ($i < $numShort) {
                $data[] = 0; // placeholder so all blocks have equal length
            }
            $blocks[] = array_merge($data, $ecc);
        }

        $result = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }
}
