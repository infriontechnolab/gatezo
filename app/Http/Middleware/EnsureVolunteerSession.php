<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Volunteers don't have accounts. Joining with the 6-digit event code stores
 * {event_id, user_id} in the session; this middleware hydrates them onto the request.
 */
class EnsureVolunteerSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session()->get('volunteer');

        if (! $session || ! ($event = Event::find($session['event_id'])) || ! ($user = User::find($session['user_id']))) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Join the event first.'], 401)
                : redirect()->route('scan.join');
        }

        $request->attributes->set('volunteerEvent', $event);
        $request->attributes->set('volunteerUser', $user);

        return $next($request);
    }
}
