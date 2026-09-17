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

        // Organizer rotated the code, or removed this volunteer → session is over.
        $pivot = $user->volunteerPivot($event);
        if (($session['code_version'] ?? 1) !== $event->volunteer_code_version || ! $pivot || $pivot->kicked_at) {
            $request->session()->forget('volunteer');

            return $request->expectsJson()
                ? response()->json(['message' => 'Your access was revoked. Join again with the current code.'], 401)
                : redirect()->route('scan.join')->with('hint', 'Your access was reset by the organizer. Join again with the current code.');
        }

        $approved = ! $event->require_volunteer_approval || $pivot->approved_at !== null;
        $request->attributes->set('volunteerEvent', $event);
        $request->attributes->set('volunteerUser', $user);
        $request->attributes->set('volunteerApproved', $approved);

        // Waiting for approval: the page renders a holding screen; data routes are refused.
        if (! $approved && $request->route()?->getName() !== 'scan.app') {
            return response()->json(['message' => 'Waiting for the organizer to approve you.', 'pending' => true], 403);
        }

        return $next($request);
    }
}
