<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Inline SVG QR codes: crisp on screen and in the print kit, no image files. */
final class Qr
{
    public static function svg(string $payload, int $size = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, margin: 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }
}
