<?php

declare(strict_types=1);

namespace Avdeb\QrPhp;

/**
 * Renders a QrMatrix as a scalable SVG document.
 */
final class SvgRenderer
{
    public static function render(QrMatrix $matrix, int $scale, int $margin, Color $foreground, Color $background): string
    {
        $dimension = $matrix->size + 2 * $margin;
        $pixels = $dimension * $scale;
        $path = '';
        for ($y = 0; $y < $matrix->size; $y++) {
            $x = 0;
            while ($x < $matrix->size) {
                if (!$matrix->isDark($x, $y)) {
                    $x++;
                    continue;
                }
                $start = $x;
                while ($x < $matrix->size && $matrix->isDark($x, $y)) {
                    $x++;
                }
                $run = $x - $start;
                $path .= sprintf('M%d %dh%dv1h-%dz', $start + $margin, $y + $margin, $run, $run);
            }
        }

        return sprintf(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">'
            . '<rect width="%d" height="%d" fill="%s"/><path fill="%s" d="%s"/></svg>' . "\n",
            $pixels,
            $pixels,
            $dimension,
            $dimension,
            $dimension,
            $dimension,
            $background->toHex(),
            $foreground->toHex(),
            $path,
        );
    }
}
