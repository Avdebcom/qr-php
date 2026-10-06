<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * An immutable RGB colour.
 */
final class Color
{
    public function __construct(
        public readonly int $red,
        public readonly int $green,
        public readonly int $blue,
    ) {
        foreach ([$red, $green, $blue] as $component) {
            if ($component < 0 || $component > 255) {
                throw new QrException('Colour components must be between 0 and 255.');
            }
        }
    }

    /** Parses "#rrggbb", "#rgb" (the leading "#" is optional). */
    public static function fromHex(string $hex): self
    {
        $value = trim($hex);
        if (str_starts_with($value, '#')) {
            $value = substr($value, 1);
        }
        if (preg_match('/^[0-9a-fA-F]{3}$/', $value) === 1) {
            $value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
        }
        if (preg_match('/^[0-9a-fA-F]{6}$/', $value) !== 1) {
            throw new QrException(sprintf('Invalid colour "%s"; expected a hex value like #000000.', $hex));
        }

        return new self(
            (int) hexdec(substr($value, 0, 2)),
            (int) hexdec(substr($value, 2, 2)),
            (int) hexdec(substr($value, 4, 2)),
        );
    }

    public function toHex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }
}
