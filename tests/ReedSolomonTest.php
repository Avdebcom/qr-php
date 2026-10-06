<?php

declare(strict_types=1);

namespace Avdeb\QrPhp\Tests;

use Avdeb\QrPhp\QrException;
use Avdeb\QrPhp\ReedSolomon;
use PHPUnit\Framework\TestCase;

final class ReedSolomonTest extends TestCase
{
    public function testGaloisFieldMultiplication(): void
    {
        self::assertSame(0x1D, ReedSolomon::multiply(0x80, 0x02));
        self::assertSame(0, ReedSolomon::multiply(0, 0xAB));
        self::assertSame(0xAB, ReedSolomon::multiply(1, 0xAB));
        self::assertSame(ReedSolomon::multiply(0x53, 0xCA), ReedSolomon::multiply(0xCA, 0x53));
    }

    public function testHelloWorldVersion1MEccCodewords(): void
    {
        // Data codewords of "HELLO WORLD" as a 1-M symbol (classic tutorial example).
        $data = [32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236, 17, 236, 17];
        $ecc = ReedSolomon::remainder($data, ReedSolomon::divisor(10));

        self::assertSame([196, 35, 39, 119, 235, 215, 231, 226, 93, 23], $ecc);
    }

    public function testInvalidDegreeThrows(): void
    {
        $this->expectException(QrException::class);
        ReedSolomon::divisor(0);
    }
}
