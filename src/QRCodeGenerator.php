<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Facade: configure once, then generate SVG or PNG QR codes.
 */
final class QRCodeGenerator
{
    public const MAX_SIZE = 100;
    public const MAX_MARGIN = 64;

    private int $size = 10;
    private int $margin = 4;
    private ErrorCorrectionLevel $errorCorrection = ErrorCorrectionLevel::Medium;
    private Color $foreground;
    private Color $background;
    private readonly Encoder $encoder;

    /**
     * @param array{size?: int, margin?: int, errorCorrection?: string|ErrorCorrectionLevel, foreground?: string, background?: string} $options
     */
    public function __construct(array $options = [])
    {
        $this->encoder = new Encoder();
        $this->foreground = new Color(0, 0, 0);
        $this->background = new Color(255, 255, 255);

        foreach ($options as $key => $value) {
            match ($key) {
                'size' => $this->setSize(self::intOption($key, $value)),
                'margin' => $this->setMargin(self::intOption($key, $value)),
                'errorCorrection' => $this->setErrorCorrection(self::levelOption($value)),
                'foreground' => $this->setForeground(self::stringOption($key, $value)),
                'background' => $this->setBackground(self::stringOption($key, $value)),
                default => throw new QrException(sprintf('Unknown option "%s".', (string) $key)),
            };
        }
    }

    /** Pixels per module (1-100). */
    public function setSize(int $size): void
    {
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw new QrException(sprintf('Size must be between 1 and %d pixels per module.', self::MAX_SIZE));
        }
        $this->size = $size;
    }

    /** Quiet zone width in modules (0-64; the standard recommends 4). */
    public function setMargin(int $margin): void
    {
        if ($margin < 0 || $margin > self::MAX_MARGIN) {
            throw new QrException(sprintf('Margin must be between 0 and %d modules.', self::MAX_MARGIN));
        }
        $this->margin = $margin;
    }

    public function setErrorCorrection(string|ErrorCorrectionLevel $level): void
    {
        $this->errorCorrection = $level instanceof ErrorCorrectionLevel ? $level : ErrorCorrectionLevel::fromString($level);
    }

    public function setForeground(string $hex): void
    {
        $this->foreground = Color::fromHex($hex);
    }

    public function setBackground(string $hex): void
    {
        $this->background = Color::fromHex($hex);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMargin(): int
    {
        return $this->margin;
    }

    public function getErrorCorrection(): ErrorCorrectionLevel
    {
        return $this->errorCorrection;
    }

    /** Encodes data into a module matrix without rendering. */
    public function encode(string $data): QrMatrix
    {
        return $this->encoder->encode($data, $this->errorCorrection);
    }

    /** Returns the image as a string; $format is "svg" or "png". */
    public function render(string $data, string $format = 'svg'): string
    {
        $normalized = strtolower($format);
        if ($normalized !== 'svg' && $normalized !== 'png') {
            throw new QrException(sprintf('Unsupported format "%s"; use "svg" or "png".', $format));
        }
        $matrix = $this->encode($data);

        return $normalized === 'svg'
            ? SvgRenderer::render($matrix, $this->size, $this->margin, $this->foreground, $this->background)
            : PngRenderer::render($matrix, $this->size, $this->margin, $this->foreground, $this->background);
    }

    /**
     * Writes a QR code to $filename; the format follows the extension (.svg or .png).
     *
     * @return bool true when the whole file was written, false if writing failed
     */
    public function generate(string $data, string $filename): bool
    {
        if (trim($filename) === '') {
            throw new QrException('Filename must not be empty.');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== 'svg' && $extension !== 'png') {
            throw new QrException(sprintf('Filename "%s" must end in .svg or .png.', $filename));
        }
        $content = $this->render($data, $extension);
        // Write errors (missing directory, permissions) are reported through the return value.
        $written = @file_put_contents($filename, $content);

        return $written === strlen($content);
    }

    private static function intOption(string $key, mixed $value): int
    {
        if (!is_int($value)) {
            throw new QrException(sprintf('Option "%s" must be an integer.', $key));
        }

        return $value;
    }

    private static function stringOption(string $key, mixed $value): string
    {
        if (!is_string($value)) {
            throw new QrException(sprintf('Option "%s" must be a string.', $key));
        }

        return $value;
    }

    private static function levelOption(mixed $value): string|ErrorCorrectionLevel
    {
        if (!is_string($value) && !$value instanceof ErrorCorrectionLevel) {
            throw new QrException('Option "errorCorrection" must be L, M, Q, H or an ErrorCorrectionLevel.');
        }

        return $value;
    }
}
