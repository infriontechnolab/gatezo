<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DrawController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\VendorController;
use App\Support\Impersonation;
use Illuminate\Support\Facades\Route;

/*
| Organizer panel   → /admin (Filament, see AdminPanelProvider)
| Attendees (public) → /e/{slug}, /pass/{code}, /stall/{code}, /e/{slug}/feedback
| Volunteers         → /scan (join by code) then /scan/app (offline-capable scanner)
*/

Route::get('/', LandingController::class)->name('landing');
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('/demo', DemoController::class)->middleware('throttle:30,1')->name('demo');

// ---- Attendees ----------------------------------------------------------
// Throttles are per IP, and at a venue hundreds of phones share the Wi-Fi NAT IP.
// Keep limits generous: they exist to stop scripts, not to stop a queue at the gate.
Route::get('/e/{event}', [PublicEventController::class, 'show'])->middleware('throttle:600,1')->name('event.show');
Route::post('/e/{event}/register', [PublicEventController::class, 'register'])->middleware('throttle:120,1')->name('event.register');
Route::get('/e/{event}/feedback', [PublicEventController::class, 'feedbackForm'])->middleware('throttle:600,1')->name('event.feedback');
Route::post('/e/{event}/feedback', [PublicEventController::class, 'feedback'])->middleware('throttle:60,1');
Route::get('/pass/{pass}', [PublicEventController::class, 'pass'])->middleware('throttle:600,1')->name('pass.show');
Route::get('/pass/{pass}/qr', [PublicEventController::class, 'passQr'])->middleware('throttle:600,1')->name('pass.qr');
Route::post('/pass/{pass}/consent', [PublicEventController::class, 'consent'])->middleware('throttle:60,1')->name('pass.consent');
Route::get('/stall/{stall}', [PublicEventController::class, 'stall'])->middleware('throttle:600,1')->name('stall.show');

// ---- Gate board (tablet at the entrance; signed link from Print & reports) ----
Route::get('/e/{event}/board', [BoardController::class, 'show'])->middleware(['signed', 'throttle:600,1'])->name('board.show');
Route::get('/e/{event}/board.json', [BoardController::class, 'json'])->middleware(['signed', 'throttle:600,1'])->name('board.json');

// ---- Lucky draw: presenter (signed) + public results -------------------------
Route::get('/e/{event}/draws', [DrawController::class, 'results'])->middleware('throttle:600,1')->name('draw.results');
Route::get('/e/{event}/draw/{draw}', [DrawController::class, 'stage'])->middleware(['signed', 'throttle:600,1'])->name('draw.stage');
Route::get('/e/{event}/draw/{draw}/state', [DrawController::class, 'stageJson'])->middleware(['signed', 'throttle:1200,1'])->name('draw.stage.json');

// ---- Volunteers ---------------------------------------------------------
Route::prefix('scan')->name('scan.')->group(function () {
    Route::get('/', [ScannerController::class, 'joinForm'])->name('join');
    Route::post('/join', [ScannerController::class, 'join'])->middleware('throttle:30,1')->name('join.post');
    Route::post('/leave', [ScannerController::class, 'leave'])->name('leave');
    Route::get('/g/{event}/{code}', [ScannerController::class, 'gateSign'])->name('gate'); // printed on gate signs
    Route::get('/i/{token}', [ScannerController::class, 'invite'])->middleware('throttle:30,1')->name('invite'); // personal link from the Shifts page

    Route::middleware('volunteer')->group(function () {
        Route::get('/app', [ScannerController::class, 'app'])->name('app');
        Route::get('/bundle', [ScannerController::class, 'bundle'])->name('bundle');
        Route::post('/sync', [ScannerController::class, 'sync'])->name('sync');
        Route::post('/duty', [ScannerController::class, 'duty'])->name('duty');
        Route::post('/claim', [ScannerController::class, 'claim'])->name('claim'); // volunteer verifies a draw winner
    });
});

// ---- Vendors (signed private link, no account) ------------------------------
Route::prefix('vendor/{stall}')->name('vendor.')->middleware(['signed', 'vendor.current'])->group(function () {
    Route::get('/', [VendorController::class, 'show'])->name('show');
    Route::get('/bundle', [VendorController::class, 'bundle'])->name('bundle');
    Route::post('/lead', [VendorController::class, 'lead'])->name('lead');
    Route::get('/leads.csv', [VendorController::class, 'leadsCsv'])->name('leads.csv');
});

// ---- Ops: end a "log in as" session and return to /ops -------------------------
Route::post('/ops/stop-impersonating', fn () => Impersonation::stop())->middleware('auth')->name('ops.stop-impersonating');

// ---- Organizer print / export (outside Filament, browser Print → PDF) -------
Route::prefix('print/{event}')->name('print.')->middleware('auth')->group(function () {
    Route::get('/kit', [PrintController::class, 'kit'])->name('kit');
    Route::get('/report', [PrintController::class, 'report'])->name('report');
    Route::get('/attendees.csv', [PrintController::class, 'attendeesCsv'])->name('attendees.csv');
    Route::get('/qr.zip', [PrintController::class, 'qrZip'])->name('qr.zip');                       // codes only, for the organizer's own designer
    Route::get('/qr/{key}.{format}', [PrintController::class, 'qr'])->where('format', 'png|svg')->name('qr');
});
