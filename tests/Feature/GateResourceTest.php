<?php

use App\Filament\Resources\Gates\Pages\CreateGate;
use App\Filament\Resources\Gates\Pages\EditGate;
use App\Filament\Resources\Gates\Pages\ListGates;
use App\Models\Gate;
use Livewire\Livewire;

it('lists only the current event\'s gates', function () {
    $theirs = Gate::factory()->create();
    $event = actingAsOrganizer();
    $ours = Gate::factory()->for($event)->create();

    Livewire::test(ListGates::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('creates a gate on the current event', function () {
    $event = actingAsOrganizer();

    Livewire::test(CreateGate::class)
        ->fillForm(['name' => 'Main Gate', 'code' => 'G1'])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(Gate::class, ['event_id' => $event->id, 'name' => 'Main Gate', 'code' => 'G1']);
});

it('requires a name and a code', function () {
    actingAsOrganizer();

    Livewire::test(CreateGate::class)
        ->fillForm(['name' => null, 'code' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'code' => 'required']);
});

it('edits a gate', function () {
    $event = actingAsOrganizer();
    $gate = Gate::factory()->for($event)->create();

    Livewire::test(EditGate::class, ['record' => $gate->id])
        ->fillForm(['name' => 'Tower B Gate'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($gate->fresh()->name)->toBe('Tower B Gate');
});

it('refuses a gate code the event already uses, with a message instead of a crash', function () {
    $event = actingAsOrganizer();
    Gate::factory()->for($event)->create(['name' => 'Main Gate', 'code' => 'G1']);

    Livewire::test(CreateGate::class)
        ->fillForm(['name' => 'Second Main Gate', 'code' => 'G1'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);

    Livewire::test(CreateGate::class)
        ->fillForm(['name' => 'Lower case', 'code' => 'g1'])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);

    expect($event->gates()->count())->toBe(1);
});

it('lets another event reuse a gate code', function () {
    Gate::factory()->create(['code' => 'G1']);
    $event = actingAsOrganizer();

    Livewire::test(CreateGate::class)
        ->fillForm(['name' => 'Main Gate', 'code' => 'G1'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($event->gates()->where('code', 'G1')->exists())->toBeTrue();
});

it('keeps a gate\'s own code valid when editing it', function () {
    $event = actingAsOrganizer();
    $gate = Gate::factory()->for($event)->create(['code' => 'G1']);

    Livewire::test(EditGate::class, ['record' => $gate->id])
        ->fillForm(['name' => 'Main Gate (north)', 'code' => 'G1'])
        ->call('save')
        ->assertHasNoFormErrors();
});
