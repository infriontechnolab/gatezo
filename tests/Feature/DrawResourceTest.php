<?php

use App\Filament\Resources\Draws\Pages\CreateDraw;
use App\Filament\Resources\Draws\Pages\EditDraw;
use App\Filament\Resources\Draws\Pages\ListDraws;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Prize;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

it('lists only the current event\'s draws', function () {
    $theirs = Event::factory()->create()->draws()->create(['name' => 'Other draw']);
    $event = actingAsOrganizer();
    $ours = $event->draws()->create(['name' => 'Grand draw']);

    Livewire::test(ListDraws::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('creates a draw with its prizes', function () {
    $event = actingAsOrganizer();
    $undoRepeaterFake = Repeater::fake();

    Livewire::test(CreateDraw::class)
        ->fillForm([
            'name' => 'Grand lucky draw',
            'prizes' => [['name' => 'Silver coin', 'quantity' => 2]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    $draw = Draw::where('event_id', $event->id)->sole();
    expect($draw->name)->toBe('Grand lucky draw');
    $this->assertDatabaseHas(Prize::class, ['draw_id' => $draw->id, 'name' => 'Silver coin', 'quantity' => 2]);
});

it('edits a draw', function () {
    $event = actingAsOrganizer();
    $draw = $event->draws()->create(['name' => 'Grand draw']);
    $draw->prizes()->create(['name' => 'Silver coin', 'quantity' => 1]);

    Livewire::test(EditDraw::class, ['record' => $draw->id])
        ->fillForm(['name' => 'Evening draw'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($draw->fresh()->name)->toBe('Evening draw');
});
