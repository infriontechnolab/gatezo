<?php

namespace App\Http\Controllers;

use App\Services\Qr;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __invoke(): View
    {
        // Real QR codes on the page: they point at the demo event so a curious visitor can scan one.
        $base = url('/e/sharad-utsav');

        return view('landing', [
            'wa' => 'https://wa.me/'.config('gatezo.whatsapp').'?text='.urlencode('Hi, I want to run my event on Gatezo.'),
            'qr' => [
                'poster' => Qr::svg($base, 240),
                'hero' => Qr::svg($base, 200), // the last word of the headline: scannable from a laptop screen
                'gate' => Qr::svg(url('/scan/g/sharad-utsav/G1'), 160),
                'pass' => Qr::svg('EQ1.DEMO1234.0000000000000000', 200), // looks like a pass; verifies as nothing
            ],
        ]);
    }
}
