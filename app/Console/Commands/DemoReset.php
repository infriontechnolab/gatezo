<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * Rebuilds the demo event from DemoSeeder. Runs nightly when the demo is enabled, so
 * whatever visitors changed during the day is gone by morning.
 */
class DemoReset extends Command
{
    protected $signature = 'gatezo:demo-reset';

    protected $description = 'Rebuild the public demo event from DemoSeeder';

    public function handle(): int
    {
        (new DemoSeeder)->setCommand($this)->run();
        $this->info('Demo event rebuilt: '.config('gatezo.demo.event'));

        return self::SUCCESS;
    }
}
