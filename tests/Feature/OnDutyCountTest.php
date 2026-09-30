<?php

use App\Enums\DutyStatus;
use App\Filament\Widgets\LiveStats;
use App\Models\User;
use Livewire\Livewire;

it('counts only volunteers whose latest duty log is on', function () {
    $event = actingAsOrganizer();
    $gate = $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);
    [$ravi, $meera, $nirav] = User::factory()->count(3)->create();
    $log = fn (User $v, DutyStatus $status) => $event->dutyLogs()->create(['volunteer_id' => $v->id, 'gate_id' => $gate->id, 'status' => $status, 'at' => now()]);

    $log($ravi, DutyStatus::On);
    $log($meera, DutyStatus::On);
    $log($meera, DutyStatus::Off); // went home
    $log($nirav, DutyStatus::Off);

    Livewire::test(LiveStats::class)->assertSee('Duplicates · On duty')->assertSeeText('0 · 1');
});
