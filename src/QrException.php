<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Thrown for invalid input: unknown options, out-of-range values or data too long for a QR code.
 */
final class QrException extends \InvalidArgumentException
{
}
