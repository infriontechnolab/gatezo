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
                'pass' => Qr::svg('EQ1.DEMO1234.0000000000000000', 200), // looks like a pass; verifies as nothing
            ],
        ]);
    }
}
