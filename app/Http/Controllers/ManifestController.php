<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web app manifest, per surface. A pass installed to the home screen must open
 * *that* pass; the volunteer scanner must open /scan. Chrome treats different `id`s
 * as different apps, so both can live on one phone.
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $start = (string) $request->query('start', '/scan');
        // Same-origin paths only; anything else falls back to the scanner.
        if (! preg_match('#^/[A-Za-z0-9/_\-.]*$#', $start)) {
            $start = '/scan';
        }
        $event = ($slug = $request->query('event')) ? Event::where('slug', $slug)->first() : null;

        $isPass = str_starts_with($start, '/pass/');
        $name = $isPass ? ($event ? $event->name.' pass' : 'My pass') : 'Gatezo scanner';
        $theme = $event?->accent_hex ?? '#E8604C';

        return response()->json([
            'id' => $start,
            'name' => $name,
            'short_name' => $isPass ? 'Pass' : 'Gatezo',
            'description' => $isPass ? 'Your entry pass. Works without internet.' : 'Volunteer scanner for Gatezo events.',
            'start_url' => $start,
            'scope' => $isPass ? $start : '/scan',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => $isPass ? '#fafaf9' : '#0a0a0a',
            'theme_color' => $theme,
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
