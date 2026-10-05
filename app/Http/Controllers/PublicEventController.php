<?php

namespace App\Http\Controllers;

use App\Enums\AttendeeSource;
use App\Enums\WinnerStatus;
use App\Models\Event;
use App\Models\Pass;
use App\Models\Stall;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use App\Services\PassToken;
use App\Services\Qr;
use App\Services\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Attendee-facing pages. No auth, rate-limited in routes. */
class PublicEventController extends Controller
{
    public function show(Event $event): View
    {
        return view('public.register', compact('event'));
    }

    public function register(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        abort_unless($event->allow_self_register, 403, 'Registration is closed for this event.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', new PersonName],
            'phone' => ['nullable', 'string', 'max:25', new PhoneNumber],
            'email' => ['nullable', 'email:rfc', 'max:120'],
        ], [
            'name.required' => 'Tell us your name so the volunteer knows who you are.',
        ]);

        if ($event->ask_marketing_opt_in) {
            $data['marketing_opt_in'] = $request->boolean('marketing_opt_in');
        }
        $attendee = $registration->register($event, $data, AttendeeSource::Online);
        $redirect = redirect()->route('pass.show', $attendee->pass);

        return $attendee->wasRecentlyCreated ? $redirect : $redirect->with('existing_pass', true);
    }

    /**
     * Registration is closed (the list came from another system), but people on it still
     * collect their pass here: name plus the phone or email they registered with.
     */
    public function find(Request $request, Event $event, Registration $registration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', new PersonName],
            'contact' => ['required', 'string', 'max:120'],
        ], [
            'contact.required' => 'Enter the phone number or email you registered with.',
        ]);
        $contact = trim($data['contact']);
        $byEmail = str_contains($contact, '@');
        $request->validate(['contact' => $byEmail ? ['email:rfc'] : [new PhoneNumber]]);

        try {
            $attendee = $registration->find($event, ['name' => $data['name'], $byEmail ? 'email' : 'phone' => $contact]);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['contact' => collect($e->errors())->flatten()->first()]);
        }
        if ($attendee?->pass === null) {
            throw ValidationException::withMessages(['contact' => 'No pass found for that name and '.($byEmail ? 'email' : 'phone').'. Use the details you registered with, or ask at the desk.']);
        }

        return redirect()->route('pass.show', $attendee->pass)->with('existing_pass', true);
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
            'win' => $pass->drawWinners()->whereIn('status', [WinnerStatus::Announced, WinnerStatus::Claimed])->with('prize')->latest('announced_at')->first(),
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
