<?php

use App\Filament\Resources\Checkins\Pages\ListCheckins;
use App\Filament\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Resources\Handouts\Pages\ListHandouts;
use App\Models\Attendee;
use App\Models\Checkin;
use App\Models\Feedback;
use App\Models\Handout;
use App\Models\Pass;
use Livewire\Livewire;

it('lists only the current event\'s check-ins', function () {
    $theirs = Checkin::factory()->create();
    $event = actingAsOrganizer();
    $ours = Checkin::factory()->for(Pass::factory()->for(Attendee::factory()->for($event)))->create();

    Livewire::test(ListCheckins::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('lists only the current event\'s feedback', function () {
    $theirs = Feedback::factory()->create();
    $event = actingAsOrganizer();
    $ours = Feedback::factory()->for($event)->create();

    Livewire::test(ListFeedback::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('lists only the current event\'s goodies handouts', function () {
    $handout = fn (Pass $pass) => Handout::create([
        'event_id' => $pass->event_id,
        'pass_id' => $pass->id,
        'scanned_at' => now(),
        'client_id' => fake()->uuid(),
    ]);
    $theirs = $handout(Pass::factory()->create());
    $event = actingAsOrganizer();
    $event->update(['goodies_enabled' => true]);
    $ours = $handout(Pass::factory()->for(Attendee::factory()->for($event))->create());

    Livewire::test(ListHandouts::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$ours])
        ->assertCanNotSeeTableRecords([$theirs]);
});
