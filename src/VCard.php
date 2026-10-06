<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Builds a vCard 3.0 payload for "contact" QR codes.
 */
final class VCard
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName = '',
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $organization = null,
        public readonly ?string $title = null,
        public readonly ?string $url = null,
    ) {
        if (trim($firstName . $lastName) === '') {
            throw new QrException('A vCard needs at least a first or last name.');
        }
        if ($email !== null && !str_contains($email, '@')) {
            throw new QrException(sprintf('Invalid e-mail address "%s".', $email));
        }
    }

    public function toString(): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . self::escape($this->lastName) . ';' . self::escape($this->firstName) . ';;;',
            'FN:' . self::escape(trim($this->firstName . ' ' . $this->lastName)),
        ];
        if ($this->phone !== null) {
            $lines[] = 'TEL;TYPE=CELL:' . self::escape($this->phone);
        }
        if ($this->email !== null) {
            $lines[] = 'EMAIL:' . self::escape($this->email);
        }
        if ($this->organization !== null) {
            $lines[] = 'ORG:' . self::escape($this->organization);
        }
        if ($this->title !== null) {
            $lines[] = 'TITLE:' . self::escape($this->title);
        }
        if ($this->url !== null) {
            $lines[] = 'URL:' . $this->url; // URIs are not text-escaped
        }
        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines) . "\r\n";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', "\r\n", "\n", "\r", ',', ';'], ['\\\\', '\n', '\n', '\n', '\,', '\;'], $value);
    }
}
