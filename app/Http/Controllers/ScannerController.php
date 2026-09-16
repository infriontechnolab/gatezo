<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;
use App\Services\PassToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Volunteer scanner. Join with the event's 6-digit code, then the scanner page
 * runs client-side (see resources/js/scanner.js) and talks to bundle/sync/duty.
 * Everything here tolerates being called late: scans are queued offline and replayed.
 */
class ScannerController extends Controller
{
    public function joinForm(): View
    {
        return view('scan.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:60'], // shown on the "who's on duty" board
        ]);

        $event = Event::where('volunteer_code', $data['code'])->first();
        if (! $event) {
            throw ValidationException::withMessages(['code' => 'No event found for that code.']);
        }

        // Synthetic user so checkins.scanned_by and duty_logs.volunteer_id have an owner.
        $volunteer = User::firstOrCreate(
            ['email' => Str::slug($data['name']).'.'.$event->id.'@'.User::VOLUNTEER_DOMAIN],
            ['name' => $data['name'], 'password' => Str::random(32)],
        );
        $event->members()->syncWithoutDetaching([$volunteer->id => ['role' => 'volunteer']]);

        $request->session()->put('volunteer', ['event_id' => $event->id, 'user_id' => $volunteer->id]);

        $pending = $request->session()->pull('pending_gate');
        if ($pending && $pending['event_id'] === $event->id) {
            $event->dutyLogs()->create([
                'volunteer_id' => $volunteer->id, 'gate_id' => $pending['gate_id'], 'status' => 'on', 'at' => now(),
            ]);
        }

        return redirect()->route('scan.app');
    }

    public function leave(Request $request): RedirectResponse
    {
        $request->session()->forget('volunteer');

        return redirect()->route('scan.join');
    }

    public function app(Request $request): View
    {
        return view('scan.app', [
            'event' => $request->attributes->get('volunteerEvent'),
            'volunteer' => $request->attributes->get('volunteerUser'),
        ]);
    }

    /**
     * Offline bundle: event secret + attendee list, cached by the scanner in IndexedDB.
     * Fetched when the scanner opens and re-fetched whenever it's online.
     */
    public function bundle(Request $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('volunteerEvent');

        $passes = $event->passes()
            ->with('attendee:id,name,ticket_type,is_vip')
            ->where('revoked', false)
            ->get(['id', 'attendee_id', 'code']);

        return response()->json([
            'event' => $event->only(['slug', 'name', 'allow_reentry', 'capacity', 'accent_hex']),
            'pass_secret' => $event->pass_secret, // for offline HMAC verification
            'gates' => $event->gates()->get(['id', 'name', 'code', 'is_entry']),
            'passes' => $passes->map(fn (Pass $p) => [
                'code' => $p->code,
                'name' => $p->attendee->name,
                'ticket_type' => $p->attendee->ticket_type,
                'is_vip' => $p->attendee->is_vip,
            ]),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sync a batch of scans. Idempotent on client_id so the queue can be replayed
     * freely. Cross-gate duplicates are flagged, never rejected.
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('volunteerEvent');
        /** @var User $volunteer */
        $volunteer = $request->attributes->get('volunteerUser');

        $data = $request->validate([
            'scans' => ['required', 'array', 'max:500'],
            'scans.*.client_id' => ['required', 'uuid'],
            'scans.*.token' => ['required', 'string', 'max:64'],
            'scans.*.gate_id' => ['nullable', 'integer'],
            'scans.*.direction' => ['required', 'in:in,out'],
            'scans.*.scanned_at' => ['required', 'date'],
        ]);

        $results = [];
        foreach ($data['scans'] as $scan) {
            $results[] = DB::transaction(function () use ($scan, $event, $volunteer) {
                if (Checkin::where('client_id', $scan['client_id'])->exists()) {
                    return ['client_id' => $scan['client_id'], 'status' => 'already_synced'];
                }
                if (! PassToken::verify($scan['token'], $event)) {
                    return ['client_id' => $scan['client_id'], 'status' => 'invalid_signature'];
                }

                $code = PassToken::parse($scan['token'])['code'];
                $pass = $event->passes()->where('code', $code)->first();
                if (! $pass || $pass->revoked) {
                    return ['client_id' => $scan['client_id'], 'status' => $pass ? 'revoked' : 'unknown_pass'];
                }

                // Same pass + direction already recorded within 10 minutes (any gate).
                $duplicate = $pass->checkins()
                    ->where('direction', $scan['direction'])
                    ->where('scanned_at', '>=', now()->parse($scan['scanned_at'])->subMinutes(10))
                    ->exists();

                $pass->checkins()->create([
                    'event_id' => $event->id,
                    'gate_id' => $scan['gate_id'] ?? null,
                    'direction' => $scan['direction'],
                    'scanned_by' => $volunteer->id,
                    'scanned_at' => $scan['scanned_at'],
                    'client_id' => $scan['client_id'],
                    'duplicate_flag' => $duplicate,
                ]);

                return ['client_id' => $scan['client_id'], 'status' => $duplicate ? 'duplicate' : 'ok'];
            });
        }

        return response()->json(['results' => $results]);
    }

    /**
     * The URL printed on a gate/zone sign. Inside the scanner app the JS intercepts
     * it and posts to duty(); this route is for the camera-app case: remember the
     * gate, send them to join (or straight to duty if already joined).
     */
    public function gateSign(Request $request, Event $event, string $code): RedirectResponse
    {
        $gate = $event->gates()->where('code', $code)->firstOrFail();
        $session = $request->session()->get('volunteer');

        if ($session && $session['event_id'] === $event->id) {
            $event->dutyLogs()->create([
                'volunteer_id' => $session['user_id'], 'gate_id' => $gate->id, 'status' => 'on', 'at' => now(),
            ]);

            return redirect()->route('scan.app')->with('duty', $gate->name);
        }

        $request->session()->put('pending_gate', ['event_id' => $event->id, 'gate_id' => $gate->id]);

        return redirect()->route('scan.join')->with('hint', "Join to check in at {$gate->name}.");
    }

    /** Volunteer scanned a gate/zone QR: "I'm on duty here". */
    public function duty(Request $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('volunteerEvent');
        /** @var User $volunteer */
        $volunteer = $request->attributes->get('volunteerUser');

        $data = $request->validate([
            'gate_id' => ['required', 'integer'],
            'status' => ['required', 'in:on,off'],
        ]);

        $gate = $event->gates()->findOrFail($data['gate_id']);

        $event->dutyLogs()->create([
            'volunteer_id' => $volunteer->id,
            'gate_id' => $gate->id,
            'status' => $data['status'],
            'at' => now(),
        ]);

        return response()->json(['ok' => true, 'gate' => $gate->only(['id', 'name', 'code'])]);
    }
}
