<?php

use App\Filament\Pages\Dashboard;
use App\Models\Event;
use App\Models\Stall;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('duplicates an event with its gates and stalls on the copy, leaving the original alone', function () {
    $event = actingAsOrganizer();
    auth()->user()->update(['plan' => 'pro']); // Free allows one event; a copy is a second one
    $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);
    $event->gates()->create(['name' => 'Tower B', 'code' => 'G2']);
    Stall::factory()->for($event)->create(['name' => 'Kesar Kulfi']);

    Livewire::test(Dashboard::class)
        ->callAction('duplicate', data: ['name' => 'Sharad Utsav 2027'])
        ->assertHasNoActionErrors();

    $copy = Event::where('name', 'Sharad Utsav 2027')->sole();
    // Read straight from the tables: the panel's tenant scope would hide the copy's rows.
    $gates = fn (Event $e) => DB::table('gates')->where('event_id', $e->id)->orderBy('code')->pluck('code')->all();
    $stalls = fn (Event $e) => DB::table('stalls')->where('event_id', $e->id)->get(['name', 'public_code']);

    expect($gates($copy))->toBe(['G1', 'G2'])
        ->and($gates($event))->toBe(['G1', 'G2'])
        ->and($stalls($copy)->pluck('name')->all())->toBe(['Kesar Kulfi'])
        ->and($stalls($event))->toHaveCount(1)
        ->and($stalls($copy)->first()->public_code)->not->toBe($stalls($event)->first()->public_code);
});
