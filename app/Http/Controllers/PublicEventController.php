<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Pass;
use App\Models\Stall;
use App\Services\PassToken;
use App\Services\Qr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Attendee-facing pages. No auth, rate-limited in routes. */
class PublicEventController extends Controller
{
    public function show(Event $event): View
    {
        return view('public.register', compact('event'));
    }

    public function register(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->allow_self_register, 403, 'Registration is closed for this event.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
        ]);

        // Same phone at the same event = same person. Hand back the existing pass
        // rather than minting a second one: "lost my pass" is the #1 gate question.
        $attendee = null;
        if (! empty($data['phone'])) {
            $attendee = $event->attendees()->where('phone', $data['phone'])->first();
        }
        $attendee ??= $event->attendees()->create($data + ['source' => 'online']);
        $pass = $attendee->pass ?? $attendee->pass()->create(['event_id' => $event->id]);

        return redirect()->route('pass.show', $pass);
    }

    public function pass(Pass $pass): View
    {
        abort_if($pass->revoked, 410, 'This pass has been revoked.');

        $token = PassToken::current($pass);

        return view('public.pass', [
            'pass' => $pass,
            'event' => $pass->event,
            'attendee' => $pass->attendee,
            'qrSvg' => Qr::svg($token),
            'secondsLeft' => PassToken::secondsLeft(),
            'win' => $pass->drawWinners()->whereIn('status', ['announced', 'claimed'])->with('prize')->latest('announced_at')->first(),
        ]);
    }

    /** Strict passes: the page polls this for the next rotating QR. */
    public function passQr(Pass $pass): JsonResponse
    {
        abort_if($pass->revoked, 410);
        abort_unless($pass->event->strict_passes, 404);

        return response()->json(['svg' => Qr::svg(PassToken::rotating($pass)), 'seconds_left' => PassToken::secondsLeft()]);
    }

    /** "Allow stalls to contact me" toggle on the pass page. Vendors can only capture opted-in attendees. */
    public function consent(Request $request, Pass $pass): RedirectResponse
    {
        $pass->attendee->update(['share_contact' => $request->boolean('share_contact')]);

        return back();
    }

    public function stall(Stall $stall): View
    {
        $stall->increment('view_count');

        return view('public.stall', ['stall' => $stall, 'event' => $stall->event]);
    }

    public function feedbackForm(Event $event): View
    {
        return view('public.feedback', compact('event'));
    }

    public function feedback(Request $request, Event $event): View
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'pass_code' => ['nullable', 'string', 'max:16'], // set when reached from the pass page
        ]);

        // Anonymous by default; only attach the attendee if they came via their pass.
        $attendeeId = null;
        if (! empty($data['pass_code'])) {
            $attendeeId = $event->passes()->where('code', $data['pass_code'])->value('attendee_id');
        }

        $values = ['rating' => $data['rating'], 'comment' => $data['comment'] ?? null];
        $attendeeId
            ? $event->feedback()->updateOrCreate(['attendee_id' => $attendeeId], $values) // one per named attendee
            : $event->feedback()->create($values);                                       // anonymous, unlimited

        return view('public.thanks', compact('event'));
    }
}
