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
            'qr' => [
                'poster' => Qr::svg($base, 240),
                'gate' => Qr::svg(url('/scan/g/sharad-utsav/G1'), 160),
                'stall' => Qr::svg(url('/stall/demo'), 160),
                'exit' => Qr::svg($base.'/feedback', 160),
            ],
        ]);
    }
}
