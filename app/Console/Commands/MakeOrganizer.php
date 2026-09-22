<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\User;
use App\Notifications\SetPassword;
use App\Support\Plan;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Hand-onboarding after a WhatsApp chat (self-serve is /admin/register):
 *
 *   php artisan gatezo:organizer "Bhavesh Patel" bhavesh@example.com --event="Sharad Utsav 2026" --phone=98xxxxxxxx
 *
 * Creates the user (or reuses one with that email), optionally creates their first event
 * with them as organizer, mails a set-password link, and prints the same link so it can be
 * pasted straight into the chat. Re-run for an existing email to issue a fresh link.
 * New accounts made this way are Pro (we already talked to them); pass --plan=free otherwise.
 */
class MakeOrganizer extends Command
{
    protected $signature = 'gatezo:organizer
        {name : Organizer\'s name}
        {email : Login email}
        {--phone= : Phone, stored on the user}
        {--event= : Create a first event with this name and make them its organizer}
        {--type=other : Event type: community, sports, festival, workshop, religious, college, other}
        {--plan=pro : Plan for a new account: free or pro (existing accounts keep theirs)}
        {--no-mail : Only print the link, do not send the email}';

    protected $description = 'Create an organizer account (invite-only sign-up) and send a set-password link';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $v = validator(
            ['email' => $email, 'type' => $this->option('type'), 'plan' => $this->option('plan')],
            ['email' => ['required', 'email'], 'type' => [Rule::in(array_keys(Event::TYPES))], 'plan' => [Rule::in(Plan::names())]],
        );
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $this->line("Existing account: {$user->name} <{$user->email}>. Issuing a fresh link.");
        } else {
            $user = User::create([
                'name' => trim($this->argument('name')),
                'email' => $email,
                'phone' => $this->option('phone'),
                'plan' => $this->option('plan'),
                'password' => str()->random(40), // never known; they set their own via the link
            ]);
            $this->info("Created {$user->name} <{$user->email}> on the {$user->plan} plan.");
        }

        $eventName = '';
        if ($name = $this->option('event')) {
            $event = Event::create(['name' => $name, 'type' => $this->option('type')]);
            $event->forceFill(['created_by' => $user->id])->save();
            $event->members()->attach($user->id, ['role' => 'organizer']);
            $eventName = $event->name;
            $this->info("Event \"{$event->name}\" created: {$event->slug} (volunteer code {$event->volunteer_code}).");
        }

        $token = Password::broker(Filament::getAuthPasswordBroker())->createToken($user);
        $notification = new SetPassword($token, $eventName);
        $notification->url = Filament::getResetPasswordUrl($token, $user);

        if (! $this->option('no-mail')) {
            $user->notify($notification);
            $mailer = config('mail.default');
            $this->line($mailer === 'log' ? 'Mail driver is "log": nothing was actually sent.' : "Set-password mail sent via {$mailer}.");
        }

        $this->newLine();
        $this->line('Set-password link (paste into WhatsApp if the mail does not land):');
        $this->line("  {$notification->url}");
        $this->line('  Sign-in page afterwards: '.url('/admin/login'));

        return self::SUCCESS;
    }
}
