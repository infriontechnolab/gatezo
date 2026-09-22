<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * "Log in as" from the Ops panel. The admin's id is parked in the session so the
 * organizer panel can show a way back; Filament's AuthenticateSession refreshes the
 * password-hash it keeps in the session at the end of the request, so the swap sticks.
 */
final class Impersonation
{
    public const KEY = 'impersonator_id';

    public static function start(User $target, ?Event $event = null): RedirectResponse
    {
        abort_unless(Auth::user()?->is_admin, 403);
        abort_if($target->is_admin || $target->isVolunteerAccount(), 403);

        $adminId = Auth::id();
        Auth::login($target);
        session()->regenerate();
        session()->put(self::KEY, $adminId);

        $panel = Filament::getPanel('admin');
        $event ??= $target->createdEvents()->latest('starts_at')->first() ?? $target->organizedEvents()->first();

        return redirect($event ? $panel->getUrl($event) : $panel->getUrl());
    }

    public static function stop(): RedirectResponse
    {
        $admin = User::find(session()->pull(self::KEY));
        abort_unless($admin?->is_admin, 403);

        Auth::login($admin);
        session()->regenerate();

        return redirect(Filament::getPanel('ops')->getUrl());
    }

    public static function admin(): ?User
    {
        $id = session(self::KEY);

        return $id ? User::find($id) : null;
    }
}
