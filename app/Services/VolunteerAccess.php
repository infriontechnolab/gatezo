<?php

namespace App\Services;

use App\Enums\DutyStatus;
use App\Enums\MemberRole;
use App\Enums\VolunteerJoinResult;
use App\Models\Event;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;

/**
 * Getting a volunteer onto the scanner: with the event's 6-digit code, or with a personal
 * invite link from the Shifts page. Every attempt is logged for the organizer.
 */
class VolunteerAccess
{
    public const DEVICE_COOKIE = 'eq_device';

    /** Wrong codes allowed per IP per hour before a 15-minute block. */
    public const MAX_WRONG_CODES = 10;

    /** @throws ValidationException when the volunteer cannot join */
    public function joinWithCode(Request $request, string $code, string $name): void
    {
        $deviceKey = $this->deviceKey($request);
        $log = fn (VolunteerJoinResult $result, ?Event $event = null, ?User $user = null) => $this->logJoin($request, $deviceKey, $result, $name, $code, $event, $user);

        // Brute-force guard: too many wrong codes from this IP → short block.
        $wrongKey = 'join-wrong:'.$request->ip();
        if (Cache::get($wrongKey, 0) >= self::MAX_WRONG_CODES) {
            $log(VolunteerJoinResult::LockedOut);
            throw ValidationException::withMessages(['code' => 'Too many wrong codes. Try again in 15 minutes.']);
        }

        $event = Event::where('volunteer_code', $code)->first();
        if (! $event) {
            Cache::add($wrongKey, 0, now()->addMinutes(15));
            Cache::increment($wrongKey);
            $log(VolunteerJoinResult::WrongCode);
            throw ValidationException::withMessages(['code' => 'No event found for that code.']);
        }

        // Organizer switched the shared code off: only personal links (Shifts page) get in.
        if (! $event->join_by_code) {
            $log(VolunteerJoinResult::CodeOff, $event);
            throw ValidationException::withMessages(['code' => 'This event uses personal links instead of a code. Ask the organizer for yours.']);
        }

        // Roster-only: the name must be on the shift roster for this event.
        if ($event->roster_only && ! $event->shifts()->forName($name)->exists()) {
            $log(VolunteerJoinResult::NotOnRoster, $event);
            throw ValidationException::withMessages(['name' => 'Your name is not on this event\'s volunteer list. Ask the organizer to add you.']);
        }

        $volunteer = $this->volunteerFor($event, $name, $deviceKey);
        if ($this->isKicked($event, $volunteer)) {
            $log(VolunteerJoinResult::Kicked, $event, $volunteer);
            throw ValidationException::withMessages(['code' => 'You were removed from this event by the organizer.']);
        }

        $this->startSession($request, $event, $volunteer, approved: ! $event->require_volunteer_approval);
        $log(VolunteerJoinResult::Ok, $event, $volunteer);
    }

    /**
     * Personal link: no code, no name to type, and approval is implied because the organizer
     * sent it to this person. The link binds to the first phone that opens it.
     *
     * @return string|null why the link is dead, or null once the volunteer is in
     */
    public function joinWithInvite(Request $request, Shift $shift): ?string
    {
        $event = $shift->event;
        $deviceKey = $this->deviceKey($request);
        $device = substr(sha1($deviceKey), 0, 12);
        $log = fn (VolunteerJoinResult $result, ?User $user = null) => $this->logJoin($request, $deviceKey, $result, $shift->volunteer_name, null, $event, $user);

        if ($shift->inviteExpired()) {
            $log(VolunteerJoinResult::InviteExpired);

            return 'This link has expired: the event is over.';
        }
        if ($shift->invite_used_at && $shift->invite_device !== $device) {
            $log(VolunteerJoinResult::InviteUsed);

            return 'This link was already opened on another phone. Ask the organizer to send you a new one.';
        }

        $volunteer = $this->volunteerFor($event, $shift->volunteer_name, $deviceKey);
        if ($this->isKicked($event, $volunteer)) {
            $log(VolunteerJoinResult::Kicked, $volunteer);

            return 'You were removed from this event by the organizer.';
        }

        if (! $shift->invite_used_at) {
            $shift->forceFill(['invite_used_at' => now(), 'invite_device' => $device])->save();
        }
        $this->startSession($request, $event, $volunteer, approved: true);
        $log(VolunteerJoinResult::Invite, $volunteer);

        return null;
    }

    private function deviceKey(Request $request): string
    {
        $deviceKey = $request->cookie(self::DEVICE_COOKIE) ?: str()->random(24);
        Cookie::queue(self::DEVICE_COOKIE, $deviceKey, 60 * 24 * 365);

        return $deviceKey;
    }

    private function logJoin(Request $request, string $deviceKey, VolunteerJoinResult $result, string $name, ?string $code, ?Event $event, ?User $user): void
    {
        VolunteerJoin::create([
            'event_id' => $event?->id, 'user_id' => $user?->id, 'name' => $name, 'code' => $code, 'result' => $result,
            'ip' => $request->ip(), 'device' => substr(sha1($deviceKey), 0, 12), 'user_agent' => str((string) $request->userAgent())->limit(250, '')->toString(), 'created_at' => now(),
        ]);
    }

    /**
     * Synthetic user so checkins.scanned_by and duty_logs.volunteer_id have an owner.
     * Identity = name + a long-lived device cookie: two volunteers called Ravi get two
     * users, and Ravi rejoining from the same phone after a session expiry gets the same one.
     */
    private function volunteerFor(Event $event, string $name, string $deviceKey): User
    {
        return User::firstOrCreate(
            ['email' => str($name)->slug().'.'.$event->id.'.'.substr(sha1($deviceKey), 0, 8).'@'.User::VOLUNTEER_DOMAIN],
            ['name' => $name, 'password' => str()->random(32)],
        );
    }

    private function isKicked(Event $event, User $volunteer): bool
    {
        return (bool) $volunteer->volunteerPivot($event)?->kicked_at;
    }

    private function startSession(Request $request, Event $event, User $volunteer, bool $approved): void
    {
        if (! $volunteer->volunteerPivot($event)) {
            $event->members()->attach($volunteer->id, ['role' => MemberRole::Volunteer, 'approved_at' => $approved ? now() : null]);
        }

        $request->session()->put('volunteer', ['event_id' => $event->id, 'user_id' => $volunteer->id, 'code_version' => $event->volunteer_code_version]);

        // Roster: attach any shifts planned under this name.
        Shift::linkVolunteer($event, $volunteer);

        $pending = $request->session()->pull('pending_gate');
        if ($pending && $pending['event_id'] === $event->id) {
            $event->dutyLogs()->create([
                'volunteer_id' => $volunteer->id, 'gate_id' => $pending['gate_id'], 'status' => DutyStatus::On, 'at' => now(),
            ]);
        }
    }
}
