<?php

declare(strict_types=1);

namespace Avdeb\QrPhp\Tests;

use Avdeb\QrPhp\Encoder;
use Avdeb\QrPhp\ErrorCorrectionLevel;
use Avdeb\QrPhp\QrException;
use Avdeb\QrPhp\QrMatrix;
use PHPUnit\Framework\TestCase;

final class EncoderTest extends TestCase
{
    public function testShortTextUsesVersionOne(): void
    {
        $matrix = (new Encoder())->encode('Hello, world!', ErrorCorrectionLevel::Medium);

        self::assertSame(1, $matrix->version);
        self::assertSame(21, $matrix->size);
        self::assertCount(21, $matrix->toArray());
    }

    public function testVersionOneCapacityBoundaries(): void
    {
        self::assertSame(17, Encoder::maxBytes(1, ErrorCorrectionLevel::Low));
        self::assertSame(7, Encoder::maxBytes(1, ErrorCorrectionLevel::High));
        self::assertSame(134, Encoder::maxBytes(6, ErrorCorrectionLevel::Low));

        $encoder = new Encoder();
        self::assertSame(1, $encoder->encode(str_repeat('a', 17), ErrorCorrectionLevel::Low)->version);
        self::assertSame(2, $encoder->encode(str_repeat('a', 18), ErrorCorrectionLevel::Low)->version);
    }

    public function testVersion40HoldsMaximumAndRejectsMore(): void
    {
        self::assertSame(2953, Encoder::maxBytes(40, ErrorCorrectionLevel::Low));

        $matrix = (new Encoder())->encode(str_repeat('a', 2953), ErrorCorrectionLevel::Low);
        self::assertSame(40, $matrix->version);
        self::assertSame(177, $matrix->size);

        $this->expectException(QrException::class);
        (new Encoder())->encode(str_repeat('a', 2954), ErrorCorrectionLevel::Low);
    }

    public function testFormatInformationMatchesStandardTable(): void
    {
        $encoder = new Encoder();
        $low = $encoder->encode('test', ErrorCorrectionLevel::Low, 0);
        $medium = $encoder->encode('test', ErrorCorrectionLevel::Medium, 0);

        self::assertSame([0x77C4, 0x77C4], self::readFormatBits($low));
        self::assertSame([0x5412, 0x5412], self::readFormatBits($medium));
    }

    public function testVersionInformationForVersion7(): void
    {
        $matrix = (new Encoder())->encode(str_repeat('x', 135), ErrorCorrectionLevel::Low);
        self::assertSame(7, $matrix->version);

        $topRight = 0;
        $bottomLeft = 0;
        for ($i = 0; $i < 18; $i++) {
            $a = $matrix->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $topRight |= ($matrix->isDark($a, $b) ? 1 : 0) << $i;
            $bottomLeft |= ($matrix->isDark($b, $a) ? 1 : 0) << $i;
        }
        self::assertSame(0x07C94, $topRight);
        self::assertSame(0x07C94, $bottomLeft);
    }

    public function testFinderTimingAndDarkModule(): void
    {
        $matrix = (new Encoder())->encode('finder');
        $size = $matrix->size;

        for ($x = 0; $x < 7; $x++) {
            self::assertTrue($matrix->isDark($x, 0));
            self::assertTrue($matrix->isDark($size - 1 - $x, 0));
            self::assertTrue($matrix->isDark($x, $size - 1));
        }
        self::assertFalse($matrix->isDark(1, 1));
        self::assertTrue($matrix->isDark(3, 3));
        self::assertFalse($matrix->isDark(7, 0));
        for ($i = 8; $i < $size - 8; $i++) {
            self::assertSame($i % 2 === 0, $matrix->isDark($i, 6));
            self::assertSame($i % 2 === 0, $matrix->isDark(6, $i));
        }
        self::assertTrue($matrix->isDark(8, $size - 8));
    }

    public function testAutomaticMaskIsValidAndForcedMaskIsKept(): void
    {
        $encoder = new Encoder();
        $auto = $encoder->encode('https://example.com');
        self::assertGreaterThanOrEqual(0, $auto->mask);
        self::assertLessThanOrEqual(7, $auto->mask);
        self::assertSame(5, $encoder->encode('https://example.com', ErrorCorrectionLevel::Medium, 5)->mask);

        $this->expectException(QrException::class);
        $encoder->encode('x', ErrorCorrectionLevel::Medium, 8);
    }

    public function testErrorCorrectionLevelParsing(): void
    {
        self::assertSame(ErrorCorrectionLevel::Quartile, ErrorCorrectionLevel::fromString(' q '));

        $this->expectException(QrException::class);
        ErrorCorrectionLevel::fromString('X');
    }

    /** @return array{int, int} both copies of the 15-bit format information */
    private static function readFormatBits(QrMatrix $m): array
    {
        $get = static fn (int $x, int $y): int => $m->isDark($x, $y) ? 1 : 0;
        $first = 0;
        for ($i = 0; $i <= 5; $i++) {
            $first |= $get(8, $i) << $i;
        }
        $first |= $get(8, 7) << 6;
        $first |= $get(8, 8) << 7;
        $first |= $get(7, 8) << 8;
        for ($i = 9; $i < 15; $i++) {
            $first |= $get(14 - $i, 8) << $i;
        }
        $second = 0;
        for ($i = 0; $i < 8; $i++) {
            $second |= $get($m->size - 1 - $i, 8) << $i;
        }
        for ($i = 8; $i < 15; $i++) {
            $second |= $get(8, $m->size - 15 + $i) << $i;
        }

        return [$first, $second];
    }
}
