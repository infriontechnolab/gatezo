<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR codes. Inline SVG for screens and the print kit; PNG (via GD, no Imagick needed)
 * for the "codes only" download that organizers hand to their own designer.
 */
final class Qr
{
    public static function svg(string $payload, int $size = 320): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, margin: 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($payload);
    }

    /**
     * Black-on-white PNG, `$size` px square, with the 4-module quiet zone the spec asks
     * for so it scans when dropped onto a busy background. Error correction M matches svg().
     */
    public static function png(string $payload, int $size = 1024): string
    {
        $matrix = Encoder::encode($payload, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth();
        $quiet = 4;
        $scale = max(1, intdiv($size, $modules + 2 * $quiet));
        $px = ($modules + 2 * $quiet) * $scale;

        $img = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefill($img, 0, 0, $white);
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $x0 = ($x + $quiet) * $scale;
                    $y0 = ($y + $quiet) * $scale;
                    imagefilledrectangle($img, $x0, $y0, $x0 + $scale - 1, $y0 + $scale - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img, null, 3);
        imagedestroy($img);

        return ob_get_clean();
    }
}
