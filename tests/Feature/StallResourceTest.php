<?php

use App\Filament\Resources\Stalls\Pages\CreateStall;
use App\Filament\Resources\Stalls\Pages\EditStall;
use App\Filament\Resources\Stalls\Pages\ListStalls;
use App\Models\Stall;
use Livewire\Livewire;

it('lists only the current event\'s stalls', function () {
    $theirs = Stall::factory()->create();
    $event = actingAsOrganizer();
    $ours = Stall::factory()->for($event)->create();

    Livewire::test(ListStalls::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('creates a stall with a public code', function () {
    $event = actingAsOrganizer();

    Livewire::test(CreateStall::class)
        ->fillForm(['name' => 'Jalaram Khaman House', 'location' => 'Row C'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Stall::class, ['event_id' => $event->id, 'name' => 'Jalaram Khaman House']);
    expect(Stall::first()->public_code)->not->toBeEmpty();
});

it('requires a stall name', function () {
    actingAsOrganizer();

    Livewire::test(CreateStall::class)
        ->fillForm(['name' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('edits a stall', function () {
    $event = actingAsOrganizer();
    $stall = Stall::factory()->for($event)->create();

    Livewire::test(EditStall::class, ['record' => $stall->getRouteKey()])
        ->fillForm(['offers' => 'Show this page for 10% off'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($stall->fresh()->offers)->toBe('Show this page for 10% off');
});
