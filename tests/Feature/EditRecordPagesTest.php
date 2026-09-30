<?php

use App\Enums\AttendeeSource;
use App\Filament\Pages\Tenancy\EditEventProfile;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Attendees\Pages\EditAttendee;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Models\Attendee;
use App\Models\Shift;
use Livewire\Livewire;

it('edits an attendee and keeps how they registered', function () {
    $event = actingAsOrganizer();
    $attendee = Attendee::factory()->for($event)->create(['source' => AttendeeSource::Online]);

    Livewire::test(EditAttendee::class, ['record' => $attendee->id])
        ->assertFormFieldIsDisabled('source')
        ->fillForm(['name' => 'Aarti Shah', 'phone' => '+91 98234 56710'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($attendee->fresh())
        ->name->toBe('Aarti Shah')
        ->phone->toBe('9823456710')
        ->source->toBe(AttendeeSource::Online);
});

it('refuses a junk attendee name on edit', function () {
    $event = actingAsOrganizer();
    $attendee = Attendee::factory()->for($event)->create();

    Livewire::test(EditAttendee::class, ['record' => $attendee->id])
        ->fillForm(['name' => '%%%$$$$'])
        ->call('save')
        ->assertHasFormErrors(['name']);
});

it('does not open another event\'s attendee', function () {
    $other = Attendee::factory()->create();
    $event = actingAsOrganizer();

    Livewire::test(EditAttendee::class, ['record' => $other->id])->assertNotFound();
    $this->get(AttendeeResource::getUrl('edit', ['record' => $other->id], tenant: $event))->assertNotFound();
});

it('edits a shift', function () {
    $event = actingAsOrganizer();
    $shift = Shift::factory()->for($event)->create();

    Livewire::test(EditShift::class, ['record' => $shift->id])
        ->fillForm(['label' => 'Registration desk'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($shift->fresh()->label)->toBe('Registration desk');
});

it('saves event settings', function () {
    $event = actingAsOrganizer();

    Livewire::test(EditEventProfile::class)
        ->fillForm(['name' => 'Property Expo 2026', 'venue' => 'Exhibition grounds'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh())
        ->name->toBe('Property Expo 2026')
        ->venue->toBe('Exhibition grounds');
});
