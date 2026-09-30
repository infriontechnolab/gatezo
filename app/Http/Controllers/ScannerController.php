<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Enums\CheckinDirection;
use App\Enums\DutyStatus;
use App\Enums\WinnerStatus;
use App\Http\Requests\SyncScansRequest;
use App\Models\DrawWinner;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Shift;
use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Services\DrawEngine;
use App\Services\PassToken;
use App\Services\Qr;
use App\Services\Registration;
use App\Services\ScannerBundle;
use App\Services\ScanRecorder;
use App\Services\VolunteerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Volunteer scanner. Join with the event's 6-digit code or a personal invite link
 * (Shifts page), then the scanner page runs client-side (see resources/js/scanner.js) and
 * talks to bundle/sync/duty. Everything here tolerates being called late: scans are queued
 * offline and replayed.
 */
class ScannerController extends Controller
{
    public function __construct(private VolunteerAccess $access) {}

    public function joinForm(): View
    {
        return view('scan.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:60', new PersonName], // shown on the "who's on duty" board
        ]);

        $this->access->joinWithCode($request, $data['code'], $data['name']);

        return redirect()->route('scan.app');
    }

    /** Personal link from the Shifts page: `/scan/i/{token}`. */
    public function invite(Request $request, string $token): RedirectResponse|View
    {
        $shift = Shift::with('event')->where('invite_token', $token)->first();
        abort_unless($shift, 404);

        $deadReason = $this->access->joinWithInvite($request, $shift);

        return $deadReason === null
            ? redirect()->route('scan.app')
            : view('scan.invite-dead', ['event' => $shift->event, 'reason' => $deadReason]);
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
     * Offline bundle: fetched when the scanner opens and re-fetched whenever it's online.
     */
    public function bundle(Request $request, ScannerBundle $bundle): JsonResponse
    {
        return response()->json($bundle->build($request->attributes->get('volunteerEvent')));
    }

    /** Sync a batch of scans from the phone's offline queue. */
    public function sync(SyncScansRequest $request, ScanRecorder $recorder): JsonResponse
    {
        $event = $request->attributes->get('volunteerEvent');
        $volunteer = $request->attributes->get('volunteerUser');

        return response()->json([
            'results' => array_map(fn (array $scan) => $recorder->record($event, $volunteer, $scan), $request->validated('scans')),
        ]);
    }

    /**
     * Someone at the gate without a pass: the volunteer registers them and they walk in.
     * Needs a connection (pass codes are made here); the phone shows the pass link as a QR
     * so they can keep their pass for re-entry, goodies and feedback.
     */
    public function walkup(Request $request, Registration $registration, ScanRecorder $recorder): JsonResponse
    {
        /** @var Event $event */
        $event = $request->attributes->get('volunteerEvent');
        abort_unless($event->allow_self_register, 403, 'Registration is closed for this event.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', new PersonName],
            'phone' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'gate_id' => ['nullable', 'integer', Rule::exists('gates', 'id')->where('event_id', $event->id)],
        ]);

        $attendee = $registration->register($event, ['name' => $data['name'], 'phone' => $data['phone'] ?? null], AttendeeSource::Walkup);
        $scan = $recorder->record($event, $request->attributes->get('volunteerUser'), [
            'client_id' => (string) str()->uuid(),
            'token' => PassToken::current($attendee->pass, $event),
            'gate_id' => $data['gate_id'] ?? null,
            'direction' => CheckinDirection::In->value,
            'scanned_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => $scan['status'],
            'client_id' => $scan['client_id'],
            'existing' => ! $attendee->wasRecentlyCreated,
            'name' => $attendee->name,
            'code' => $attendee->pass->code,
            'qr' => Qr::svg(route('pass.show', $attendee->pass), 240),
        ]);
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
                'volunteer_id' => $session['user_id'], 'gate_id' => $gate->id, 'status' => DutyStatus::On, 'at' => now(),
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
            ->whereHas('pass', fn ($p) => $p->where('code', $code))->where('status', WinnerStatus::Announced)->with('prize')->first();
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
            'status' => ['required', Rule::enum(DutyStatus::class)],
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
