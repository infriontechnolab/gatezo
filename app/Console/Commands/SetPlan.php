<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Plan;
use Illuminate\Console\Command;
use Illuminate\Validation\Rule;

/**
 * Set an organizer's base plan: `pro` here means Pro with no end date (comped accounts).
 * Paid, dated Pro is recorded in Ops → Subscriptions instead.
 *
 *   php artisan gatezo:plan bhavesh@example.com pro
 *
 * No argument for the plan just shows where they stand.
 */
class SetPlan extends Command
{
    protected $signature = 'gatezo:plan {email : Organizer login} {plan? : free or pro}';

    protected $description = 'Show or change an organizer\'s plan';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }

        if ($plan = $this->argument('plan')) {
            if (validator(['plan' => $plan], ['plan' => [Rule::in(Plan::names())]])->fails()) {
                $this->error('Plan must be one of: '.implode(', ', Plan::names()));

                return self::INVALID;
            }
            $user->update(['plan' => $plan]);
            $this->info("{$user->name} <{$user->email}> is now on {$plan}.");
        }

        $events = Plan::eventsUsed($user);
        $limit = Plan::eventLimit($user);
        $until = $user->paidUntil();
        $this->line("Plan: {$user->currentPlan()}".($until ? " until {$until->format('j M Y')}" : '')." · events created: {$events}".($limit === null ? '' : " of {$limit}").' · phone: '.($user->phone ?: '-'));

        return self::SUCCESS;
    }
}
