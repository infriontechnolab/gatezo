<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One link a prospect can open from WhatsApp: signs them into the shared demo organizer
 * account and lands on the demo event's dashboard. 404 unless the demo is enabled and seeded.
 */
class DemoController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('gatezo.demo.enabled'), 404);

        $user = User::where('email', config('gatezo.demo.email'))->first();
        $event = Event::where('slug', config('gatezo.demo.event'))->first();
        abort_unless($user && $event, 404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to("/admin/{$event->slug}");
    }
}
