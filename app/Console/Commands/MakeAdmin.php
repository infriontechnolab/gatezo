<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grant (or revoke) access to the /ops panel:
 *
 *   php artisan gatezo:admin you@example.com
 *   php artisan gatezo:admin you@example.com --revoke
 *
 * The account must already exist (sign up at /admin/register or use gatezo:organizer).
 * Staff accounts are not listed as organizers in Ops and cannot be impersonated.
 */
class MakeAdmin extends Command
{
    protected $signature = 'gatezo:admin {email : Existing login} {--revoke : Take Ops access away}';

    protected $description = 'Give an account access to the /ops panel';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('No account with that email. Create one first: /admin/register or gatezo:organizer.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info($user->is_admin ? "{$user->name} can now open ".url('/ops').'.' : "{$user->name} no longer has Ops access.");

        return self::SUCCESS;
    }
}
