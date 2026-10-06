<?php

declare(strict_types=1);

namespace Avdeb\QrPhp\Tests;

use Avdeb\QrPhp\Encoder;
use Avdeb\QrPhp\ErrorCorrectionLevel;
use Avdeb\QrPhp\QRCodeGenerator;
use Avdeb\QrPhp\QrException;
use Avdeb\QrPhp\VCard;
use PHPUnit\Framework\TestCase;

final class VCardTest extends TestCase
{
    public function testBuildsEscapedVCard(): void
    {
        $card = new VCard('Jane', 'Doe', phone: '+15551234567', email: 'jane@example.com', organization: 'Acme, Inc.');

        $expected = "BEGIN:VCARD\r\nVERSION:3.0\r\nN:Doe;Jane;;;\r\nFN:Jane Doe\r\n"
            . "TEL;TYPE=CELL:+15551234567\r\nEMAIL:jane@example.com\r\nORG:Acme\\, Inc.\r\nEND:VCARD\r\n";
        self::assertSame($expected, $card->toString());
        self::assertSame($expected, (string) $card);
    }

    public function testVCardCanBeEncoded(): void
    {
        $payload = (new VCard('Jane', 'Doe'))->toString();
        // 13 (BEGIN) + 13 (VERSION) + 15 (N) + 13 (FN) + 11 (END) bytes, CRLF line endings included.
        self::assertSame(65, strlen($payload));

        // Byte-mode capacity at level M: version 4 holds 62 bytes, version 5 holds 84.
        self::assertSame(62, Encoder::maxBytes(4, ErrorCorrectionLevel::Medium));
        self::assertSame(84, Encoder::maxBytes(5, ErrorCorrectionLevel::Medium));

        $matrix = (new QRCodeGenerator())->encode($payload);
        self::assertSame(5, $matrix->version);
        self::assertSame(37, $matrix->size);
    }

    public function testEmptyNameThrows(): void
    {
        $this->expectException(QrException::class);
        new VCard('  ', '');
    }
}
