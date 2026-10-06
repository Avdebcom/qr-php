<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Reed-Solomon error correction over GF(2^8) with the QR polynomial x^8+x^4+x^3+x^2+1 (0x11D).
 */
final class ReedSolomon
{
    public static function multiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    /**
     * Generator polynomial coefficients (highest power first, leading 1 omitted).
     *
     * @return list<int>
     */
    public static function divisor(int $degree): array
    {
        if ($degree < 1 || $degree > 255) {
            throw new QrException('Reed-Solomon degree must be between 1 and 255.');
        }
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::multiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::multiply($root, 0x02);
        }

        return $result;
    }

    /**
     * Error correction codewords for $data.
     *
     * @param list<int> $data
     * @param list<int> $divisor
     * @return list<int>
     */
    public static function remainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            if (!is_int($byte) || $byte < 0 || $byte > 255) {
                throw new QrException('Data codewords must be integers between 0 and 255.');
            }
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::multiply($coefficient, $factor);
            }
        }

        return $result;
    }
}
