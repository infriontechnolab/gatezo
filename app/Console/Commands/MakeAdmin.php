<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;

/**
 * Staff access to the /ops panel:
 *
 *   php artisan gatezo:admin you@example.com --name="Umesh"   # new account: prints a set-password link
 *   php artisan gatezo:admin you@example.com                  # existing account: grant
 *   php artisan gatezo:admin you@example.com --revoke
 *
 * Staff accounts never open /admin (they "log in as" an organizer from Ops instead),
 * are hidden from the Ops organizer list, and cannot be impersonated.
 */
class MakeAdmin extends Command
{
    protected $signature = 'gatezo:admin {email : Login email} {--name= : Create the account with this name if it does not exist} {--revoke : Take Ops access away}';

    protected $description = 'Give an account access to the /ops panel (creates it with --name)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user && $this->option('revoke')) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        if (! $user) {
            if (! $this->option('name')) {
                $this->error('No account with that email. Add --name="Their Name" to create one.');

                return self::FAILURE;
            }
            $user = User::create(['name' => trim($this->option('name')), 'email' => $email, 'plan' => 'pro', 'password' => str()->random(40)]);
            $this->info("Created {$user->name} <{$user->email}>.");
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        if (! $user->is_admin) {
            $this->info("{$user->name} no longer has Ops access.");

            return self::SUCCESS;
        }

        $this->info("{$user->name} can open ".Filament::getPanel('ops')->getUrl().'.');
        if ($user->wasRecentlyCreated) {
            $token = Password::broker()->createToken($user);
            $this->newLine();
            $this->line('Set a password here first (valid 60 minutes):');
            $this->line('  '.Filament::getPanel('ops')->getResetPasswordUrl($token, $user));
        }
        if ($user->createdEvents()->exists()) {
            $this->warn('This account created events as an organizer. Staff cannot open /admin; use "Log in as" from Ops, or move those events to a client account.');
        }

        return self::SUCCESS;
    }
}
