# avdeb/qr-php

[![Packagist](https://img.shields.io/packagist/v/avdeb/qr-php)](https://packagist.org/packages/avdeb/qr-php) [![CI](https://github.com/avdeb/qr-php/actions/workflows/ci.yml/badge.svg)](https://github.com/avdeb/qr-php/actions/workflows/ci.yml) ![License: MIT](https://img.shields.io/badge/license-MIT-blue)

Simple PHP library for generating QR codes.

Try it online: https://avdeb.com/qr-code-generator/

## Install

```sh
composer require avdeb/qr-php
```

## Usage

### Save a QR code to a file

```php
<?php
require 'vendor/autoload.php';

use Avdeb\QrPhp\QRCodeGenerator;

$qr = new QRCodeGenerator(['size' => 8, 'margin' => 4, 'errorCorrection' => 'M']);

// Format follows the extension: .svg or .png
if (!$qr->generate('https://example.com', __DIR__ . '/example.png')) {
    echo "Could not write file\n";
}
$qr->generate('https://example.com', __DIR__ . '/example.svg');
```

### Serve a QR code dynamically (no file on disk)

```php
<?php
require 'vendor/autoload.php';

use Avdeb\QrPhp\QRCodeGenerator;

$qr = new QRCodeGenerator(['size' => 6, 'foreground' => '#1a237e']);
$text = (string) ($_GET['text'] ?? 'Hello, world!');

header('Content-Type: image/png');
echo $qr->render($text, 'png');
// or: header('Content-Type: image/svg+xml'); echo $qr->render($text, 'svg');
```

### Contact card (vCard) QR code

```php
<?php
require 'vendor/autoload.php';

use Avdeb\QrPhp\QRCodeGenerator;
use Avdeb\QrPhp\VCard;

$card = new VCard(
    firstName: 'Jane',
    lastName: 'Doe',
    phone: '+15551234567',
    email: 'jane@example.com',
    organization: 'Acme, Inc.',
);

$qr = new QRCodeGenerator(['errorCorrection' => 'Q']);
$qr->generate($card->toString(), __DIR__ . '/jane.svg');
```

## API

### `QRCodeGenerator` (final)

| Method | Description |
|---|---|
| `__construct(array $options = [])` | Options: `size` (int, px per module, 1–100, default 10), `margin` (int, quiet-zone modules, 0–64, default 4), `errorCorrection` (`'L'`/`'M'`/`'Q'`/`'H'` or `ErrorCorrectionLevel`, default M), `foreground` / `background` (hex colour, default `#000000` / `#ffffff`). Unknown keys or wrong types throw `QrException`. |
| `generate(string $data, string $filename): bool` | Writes an SVG or PNG (chosen by extension). Returns `false` if the file could not be written; throws `QrException` for bad extension, empty filename or data too long. |
| `render(string $data, string $format = 'svg'): string` | Returns the image bytes (`svg` or `png`). |
| `encode(string $data): QrMatrix` | Encodes without rendering. |
| `setSize(int $size): void` / `getSize(): int` | Pixels per module. |
| `setMargin(int $margin): void` / `getMargin(): int` | Quiet zone in modules. |
| `setErrorCorrection(string\|ErrorCorrectionLevel $level): void` / `getErrorCorrection(): ErrorCorrectionLevel` | Error correction level. |
| `setForeground(string $hex): void`, `setBackground(string $hex): void` | `#rrggbb` or `#rgb`. |

### `Encoder` (final)

- `encode(string $data, ErrorCorrectionLevel $ecl = Medium, ?int $mask = null): QrMatrix` — picks the smallest version 1–40; `$mask` forces pattern 0–7, `null` selects by penalty score.
- `static maxBytes(int $version, ErrorCorrectionLevel $ecl): int` — byte capacity (e.g. version 1-L = 17, version 40-L = 2953).
- `static rawDataModules(int $version): int` — data+ECC module count.

### `QrMatrix` (final, immutable)

- Properties: `version`, `size`, `errorCorrection`, `mask`.
- `isDark(int $x, int $y): bool` (throws when out of range), `toArray(): list<list<bool>>` (`[y][x]`), `toText(string $dark = '##', string $light = '  '): string`.

### `ErrorCorrectionLevel` (enum: `Low`='L', `Medium`='M', `Quartile`='Q', `High`='H')

- `static fromString(string $level): self`, `formatBits(): int`.

### `VCard` (final, immutable)

- `__construct(string $firstName, string $lastName = '', ?string $phone = null, ?string $email = null, ?string $organization = null, ?string $title = null, ?string $url = null)`
- `toString(): string` / `__toString()` — vCard 3.0 with CRLF line endings and escaped text.

### Renderers

- `SvgRenderer::render(QrMatrix, int $scale, int $margin, Color $fg, Color $bg): string`
- `PngRenderer::render(QrMatrix, int $scale, int $margin, Color $fg, Color $bg): string` — 1-bit palette PNG, no GD needed.
- `PngRenderer::zlibStored(string $raw): string` — uncompressed zlib stream (fallback when ext-zlib is absent).

### `Color` (final, immutable)

- `__construct(int $red, int $green, int $blue)`, `static fromHex(string $hex): self`, `toHex(): string`.

### `QrException`

Extends `InvalidArgumentException`; thrown for all invalid input.

## Notes

- Encoding uses byte mode only (ISO/IEC 18004, versions 1–40, levels L/M/Q/H). Strings are encoded as their raw bytes, so UTF-8 text works with modern scanners; no ECI header is written. Numeric/alphanumeric/Kanji mode optimisation is not implemented, so purely numeric data uses a slightly larger symbol than necessary.
- Capacity at version 40: 2953 bytes (L), 2331 (M), 1663 (Q), 1273 (H). Longer data throws `QrException`.
- `size` is pixels per module; image width = (modules + 2 × margin) × size. Keep margin ≥ 4 for reliable scanning.
- PNG output needs no GD/Imagick. If ext-zlib is present the image is compressed, otherwise stored uncompressed (still small, as it is 1 bit per pixel).
- Logo embedding is not built in. A common approach is to generate with `errorCorrection => 'H'` and overlay a small logo (well under ~10% of the area, away from the three finder squares) with your image tool, then test-scan.
- This library only generates codes. Scanning from a webcam happens in the browser (camera via `getUserMedia` plus a JavaScript QR decoder); PHP cannot access the user's camera.

## Contributing

Issues and pull requests are welcome at https://github.com/avdeb/qr-php/issues. Run the tests with `composer install && vendor/bin/phpunit`.

## License

MIT © 2026 AVDEB
