<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Renders a QrMatrix as a 1-bit palette PNG without GD or Imagick.
 */
final class PngRenderer
{
    public static function render(QrMatrix $matrix, int $scale, int $margin, Color $foreground, Color $background): string
    {
        $dimension = $matrix->size + 2 * $margin;
        $pixels = $dimension * $scale;
        $rowBytes = intdiv($pixels + 7, 8);

        $raw = '';
        for ($moduleY = 0; $moduleY < $dimension; $moduleY++) {
            $bytes = array_fill(0, $rowBytes, 0);
            $qy = $moduleY - $margin;
            if ($qy >= 0 && $qy < $matrix->size) {
                for ($x = 0; $x < $pixels; $x++) {
                    $qx = intdiv($x, $scale) - $margin;
                    if ($qx >= 0 && $qx < $matrix->size && $matrix->isDark($qx, $qy)) {
                        $bytes[$x >> 3] |= 0x80 >> ($x & 7);
                    }
                }
            }
            // filter type 0 (none) followed by the packed pixels, repeated for each pixel row
            $raw .= str_repeat("\0" . pack('C*', ...$bytes), $scale);
        }

        $header = pack('NNCCCCC', $pixels, $pixels, 1, 3, 0, 0, 0);
        $palette = pack(
            'C6',
            $background->red,
            $background->green,
            $background->blue,
            $foreground->red,
            $foreground->green,
            $foreground->blue,
        );

        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', $header)
            . self::chunk('PLTE', $palette)
            . self::chunk('IDAT', self::zlib($raw))
            . self::chunk('IEND', '');
    }

    /** A valid zlib stream using uncompressed (stored) deflate blocks; needs no extension. */
    public static function zlibStored(string $raw): string
    {
        $out = "\x78\x01";
        $length = strlen($raw);
        $offset = 0;
        do {
            $block = substr($raw, $offset, 65535);
            $blockLength = strlen($block);
            $offset += $blockLength;
            $final = $offset >= $length ? 1 : 0;
            $out .= chr($final) . pack('v', $blockLength) . pack('v', $blockLength ^ 0xFFFF) . $block;
        } while ($offset < $length);

        return $out . hash('adler32', $raw, true);
    }

    private static function zlib(string $raw): string
    {
        if (function_exists('gzcompress')) {
            $compressed = gzcompress($raw, 9);
            if ($compressed !== false) {
                return $compressed;
            }
        }

        return self::zlibStored($raw);
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
