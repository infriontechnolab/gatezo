<?php

use Illuminate\Support\Facades\Schedule;

// Shared demo account: put the event back the way the seeder made it, every night.
if (config('gatezo.demo.enabled')) {
    Schedule::command('gatezo:demo-reset')->dailyAt(config('gatezo.demo.reset_at'));
}
