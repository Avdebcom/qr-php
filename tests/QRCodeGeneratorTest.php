<?php

declare(strict_types=1);

namespace Avdeb\QrPhp\Tests;

use Avdeb\QrPhp\ErrorCorrectionLevel;
use Avdeb\QrPhp\PngRenderer;
use Avdeb\QrPhp\QRCodeGenerator;
use Avdeb\QrPhp\QrException;
use PHPUnit\Framework\TestCase;

final class QRCodeGeneratorTest extends TestCase
{
    public function testSvgDimensionsFollowSizeAndMargin(): void
    {
        $generator = new QRCodeGenerator(['size' => 5, 'margin' => 4, 'foreground' => '#123', 'errorCorrection' => 'L']);
        $svg = $generator->render('Hello', 'svg');

        self::assertSame(ErrorCorrectionLevel::Low, $generator->getErrorCorrection());
        self::assertStringContainsString('viewBox="0 0 29 29"', $svg);
        self::assertStringContainsString('width="145" height="145"', $svg);
        self::assertStringContainsString('fill="#112233"', $svg);
    }

    public function testPngHeaderAndDimensions(): void
    {
        $generator = new QRCodeGenerator();
        $generator->setSize(2);
        $generator->setMargin(4);
        $png = $generator->render('Hello', 'png');

        self::assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
        self::assertSame('IHDR', substr($png, 12, 4));
        self::assertSame([1 => 58, 2 => 58], unpack('N2', substr($png, 16, 8)));
        self::assertSame('IEND', substr($png, -8, 4));
    }

    public function testGenerateWritesFile(): void
    {
        $file = sys_get_temp_dir() . '/qrphp_' . uniqid('', true) . '.svg';
        try {
            self::assertTrue((new QRCodeGenerator())->generate('https://example.com', $file));
            self::assertStringStartsWith('<?xml', (string) file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    public function testGenerateReturnsFalseWhenDirectoryIsMissing(): void
    {
        $file = sys_get_temp_dir() . '/qrphp_missing_' . uniqid('', true) . '/code.png';
        self::assertFalse((new QRCodeGenerator())->generate('data', $file));
    }

    public function testUnsupportedExtensionThrows(): void
    {
        $this->expectException(QrException::class);
        (new QRCodeGenerator())->generate('data', 'code.jpg');
    }

    public function testInvalidConfigurationThrows(): void
    {
        $generator = new QRCodeGenerator();
        foreach ([
            static fn () => $generator->setSize(0),
            static fn () => $generator->setMargin(-1),
            static fn () => new QRCodeGenerator(['colour' => 'red']),
            static fn () => new QRCodeGenerator(['size' => '10']),
            static fn () => $generator->setForeground('#12345'),
        ] as $i => $call) {
            try {
                $call();
                self::fail(sprintf('Case %d should have thrown.', $i));
            } catch (QrException) {
                self::assertTrue(true);
            }
        }
        self::assertSame(10, $generator->getSize());
        self::assertSame(4, $generator->getMargin());
    }

    public function testStoredZlibStreamRoundTrips(): void
    {
        if (!function_exists('gzuncompress')) {
            self::markTestSkipped('zlib extension not available to verify the stream.');
        }
        $payload = str_repeat('QR', 40000); // spans two stored blocks
        self::assertSame($payload, gzuncompress(PngRenderer::zlibStored($payload)));
    }
}
