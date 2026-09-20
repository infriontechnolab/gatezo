<?php

namespace App\Http\Controllers;

use App\Models\Checkin;
use App\Models\DrawWinner;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJoin;
use App\Services\DrawEngine;
use App\Services\PassToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
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
    public const DEVICE_COOKIE = 'eq_device';

    public function joinForm(): View
    {
        return view('scan.join');
    }

    /** Wrong codes allowed per IP per hour before a 15-minute block. */
    public const MAX_WRONG_CODES = 10;

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:60'], // shown on the "who's on duty" board
        ]);

        $deviceKey = $request->cookie(self::DEVICE_COOKIE) ?: Str::random(24);
        Cookie::queue(self::DEVICE_COOKIE, $deviceKey, 60 * 24 * 365);
        $log = fn (string $result, ?Event $event = null, ?User $user = null) => VolunteerJoin::create([
            'event_id' => $event?->id, 'user_id' => $user?->id, 'name' => $data['name'], 'code' => $data['code'], 'result' => $result,
            'ip' => $request->ip(), 'device' => substr(sha1($deviceKey), 0, 12), 'user_agent' => Str::limit((string) $request->userAgent(), 250, ''), 'created_at' => now(),
        ]);

        // Brute-force guard: too many wrong codes from this IP → short block.
        $wrongKey = 'join-wrong:'.$request->ip();
        if (Cache::get($wrongKey, 0) >= self::MAX_WRONG_CODES) {
            $log('locked_out');
            throw ValidationException::withMessages(['code' => 'Too many wrong codes. Try again in 15 minutes.']);
        }

        $event = Event::where('volunteer_code', $data['code'])->first();
        if (! $event) {
            Cache::add($wrongKey, 0, now()->addMinutes(15));
            Cache::increment($wrongKey);
            $log('wrong_code');
            throw ValidationException::withMessages(['code' => 'No event found for that code.']);
        }

        // Roster-only: the name must be on the shift roster for this event.
        if ($event->roster_only && ! $event->shifts()->forName($data['name'])->exists()) {
            $log('not_on_roster', $event);
            throw ValidationException::withMessages(['name' => 'Your name is not on this event\'s volunteer list. Ask the organizer to add you.']);
        }

        // Synthetic user so checkins.scanned_by and duty_logs.volunteer_id have an owner.
        // Identity = name + a long-lived device cookie: two volunteers called Ravi get two
        // users, and Ravi rejoining from the same phone after a session expiry gets the same one.
        $volunteer = User::firstOrCreate(
            ['email' => Str::slug($data['name']).'.'.$event->id.'.'.substr(sha1($deviceKey), 0, 8).'@'.User::VOLUNTEER_DOMAIN],
            ['name' => $data['name'], 'password' => Str::random(32)],
        );

        $pivot = $volunteer->volunteerPivot($event);
        if ($pivot?->kicked_at) {
            $log('kicked', $event, $volunteer);
            throw ValidationException::withMessages(['code' => 'You were removed from this event by the organizer.']);
        }
        if (! $pivot) {
            $event->members()->attach($volunteer->id, [
                'role' => 'volunteer',
                'approved_at' => $event->require_volunteer_approval ? null : now(),
            ]);
        }

        $request->session()->put('volunteer', ['event_id' => $event->id, 'user_id' => $volunteer->id, 'code_version' => $event->volunteer_code_version]);
        $log('ok', $event, $volunteer);

        // Roster: attach any shifts planned under this name.
        Shift::linkVolunteer($event, $volunteer);

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
        $event = $request->attributes->get('volunteerEvent');
        $volunteer = $request->attributes->get('volunteerUser');

        if (! $request->attributes->get('volunteerApproved')) {
            return view('scan.waiting', ['event' => $event, 'volunteer' => $volunteer]);
        }

        return view('scan.app', [
            'event' => $event,
            'volunteer' => $volunteer,
            'shift' => Shift::currentFor($event, $volunteer),
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
        $state = self::passStates($event);

        // No secret leaves the server: each cached pass carries its own signature, so the
        // phone can verify offline by comparison but cannot forge a pass it hasn't seen.
        return response()->json([
            'event' => $event->only(['slug', 'name', 'allow_reentry', 'strict_passes', 'capacity', 'accent_hex']),
            'gates' => $event->gates()->get(['id', 'name', 'code', 'is_entry']),
            // Draw winners waiting on stage: scanning their pass shows WINNER + a Claim button.
            'winners' => DrawWinner::whereHas('draw', fn ($d) => $d->where('event_id', $event->id))->where('status', 'announced')
                ->with(['pass:id,code', 'prize:id,name'])->get()->mapWithKeys(fn ($w) => [$w->pass->code => ['id' => $w->id, 'prize' => $w->prize->name]]),
            'passes' => $passes->map(fn (Pass $p) => [
                'code' => $p->code,
                'sig' => PassToken::sign($p->code, $event->pass_secret),
                'name' => $p->attendee->name,
                'ticket_type' => $p->attendee->ticket_type,
                'is_vip' => $p->attendee->is_vip,
                // Where this pass stands right now, so the phone can say "already inside" offline.
                'inside' => ($state[$p->id]['direction'] ?? null) === 'in',
                'entered' => isset($state[$p->id]),
                'last_at' => $state[$p->id]['at'] ?? null,
                'last_gate' => $state[$p->id]['gate'] ?? null,
            ]),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Latest real movement (in/out, never denied) per pass: [pass_id => [direction, at, gate]].
     *
     * @return array<int, array{direction: string, at: string, gate: ?string}>
     */
    public static function passStates(Event $event): array
    {
        $rows = DB::select(
            'SELECT t.pass_id, t.direction, t.scanned_at, g.name AS gate FROM (
                SELECT pass_id, direction, gate_id, scanned_at,
                       ROW_NUMBER() OVER (PARTITION BY pass_id ORDER BY scanned_at DESC, id DESC) AS rn
                FROM checkins WHERE event_id = ? AND direction <> \'denied\'
             ) t LEFT JOIN gates g ON g.id = t.gate_id WHERE t.rn = 1',
            [$event->id],
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->pass_id] = ['direction' => $r->direction, 'at' => Carbon::parse($r->scanned_at)->toIso8601String(), 'gate' => $r->gate];
        }

        return $out;
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
            // Set when the phone showed "already inside" and the volunteer chose.
            'scans.*.decision' => ['nullable', 'in:let_in,turned_away'],
        ]);

        $results = [];
        foreach ($data['scans'] as $scan) {
            $results[] = DB::transaction(function () use ($scan, $event, $volunteer) {
                if (Checkin::where('client_id', $scan['client_id'])->exists()) {
                    return ['client_id' => $scan['client_id'], 'status' => 'already_synced'];
                }
                if (! PassToken::verify($scan['token'], $event, strtotime($scan['scanned_at']))) {
                    return ['client_id' => $scan['client_id'], 'status' => PassToken::failure($scan['token'], $event)];
                }

                $code = PassToken::parse($scan['token'])['code'];
                $pass = $event->passes()->where('code', $code)->first();
                if (! $pass || $pass->revoked) {
                    return ['client_id' => $scan['client_id'], 'status' => $pass ? 'revoked' : 'unknown_pass'];
                }

                // Duplicate = this scan does not move the person. Entry while already inside
                // (or any second entry when re-entry is off), exit while already outside.
                // A forwarded screenshot shows up here: same pass, second "in", never an "out".
                $last = $pass->checkins()->where('direction', '<>', 'denied')->orderByDesc('scanned_at')->orderByDesc('id')->first();
                $duplicate = match ($scan['direction']) {
                    'in' => $last?->direction === 'in' || (! $event->allow_reentry && $last !== null),
                    'out' => $last === null || $last->direction === 'out',
                };
                $decision = $scan['decision'] ?? null;
                if ($decision !== null && ! $duplicate) {
                    $decision = null; // the phone thought it was a duplicate, the server knows better
                }

                $pass->checkins()->create([
                    'event_id' => $event->id,
                    'gate_id' => $scan['gate_id'] ?? null,
                    'direction' => $decision === 'turned_away' ? 'denied' : $scan['direction'],
                    'scanned_by' => $volunteer->id,
                    'scanned_at' => $scan['scanned_at'],
                    'client_id' => $scan['client_id'],
                    'duplicate_flag' => $duplicate,
                    'decision' => $decision,
                ]);

                return ['client_id' => $scan['client_id'], 'status' => $decision === 'turned_away' ? 'turned_away' : ($duplicate ? 'duplicate' : 'ok')];
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

    /** Volunteer verifies a draw winner at the stage by scanning their pass. */
    public function claim(Request $request): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('volunteerEvent');
        /** @var User $volunteer */
        $volunteer = $request->attributes->get('volunteerUser');

        $data = $request->validate(['token' => ['required', 'string', 'max:64']]);
        if (! PassToken::verify($data['token'], $event)) {
            return response()->json(['status' => 'invalid_signature'], 422);
        }
        $code = PassToken::parse($data['token'])['code'];
        $w = DrawWinner::whereHas('draw', fn ($d) => $d->where('event_id', $event->id))
            ->whereHas('pass', fn ($p) => $p->where('code', $code))->where('status', 'announced')->with('prize')->first();
        if (! $w) {
            return response()->json(['status' => 'not_a_winner'], 404);
        }
        DrawEngine::claim($w, $volunteer);

        return response()->json(['status' => 'ok', 'prize' => $w->prize->name]);
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
