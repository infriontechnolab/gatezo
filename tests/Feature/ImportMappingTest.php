<?php

use App\Enums\AttendeeSource;
use App\Filament\Resources\Attendees\Pages\ListAttendees;
use App\Models\Event;
use App\Services\AttendeeImporter;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

function csvFile(string $csv): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    return $path;
}

it('imports any template once the organizer says which column is which', function () {
    $event = Event::factory()->create();
    $path = csvFile("Booking Ref,Naam,Sampark,Booking State\nTS-1,Aarti Shah,98240 17351,Confirmed\nTS-2,Bina Rao,98240 17352,No show\n");

    expect(AttendeeImporter::guess(AttendeeImporter::read($path)['header']))->not->toHaveKey('name');

    $stats = AttendeeImporter::import($event, $path, ['name' => 'Naam', 'phone' => 'Sampark', 'status' => 'Booking State', 'external_id' => 'Booking Ref'], ['No show']);

    expect($stats)->created->toBe(1)->skipped->toBe(1)
        ->and($event->attendees()->sole())->name->toBe('Aarti Shah')->phone->toBe('9824017351')->external_id->toBe('TS-1');
});

it('imports a status the organizer chose to keep, even a cancellation word', function () {
    $event = Event::factory()->create();
    $path = csvFile("name,status\nAarti Shah,Refunded\nBina Rao,Attending\n");

    expect(AttendeeImporter::import($event, $path, ['name' => 'name', 'status' => 'status'], [])['created'])->toBe(2);
});

it('matches a re-import by the other system\'s ID, then email, and never wipes with a blank cell', function () {
    $event = Event::factory()->create();
    AttendeeImporter::import($event, csvFile("Attendee #,Name,Phone,Email\n5001,Aarti Shah,9824017351,aarti@example.com\n5002,Bina Rao,,bina@example.com\n"));

    $stats = AttendeeImporter::import($event, csvFile("Attendee #,Name,Phone,Email\n5001,Aarti Shah,9824017399,\n,Bina Rao,,BINA@example.com\n"));

    expect($stats)->created->toBe(0)->updated->toBe(2)
        ->and($event->attendees()->count())->toBe(2);
    expect($event->attendees()->where('external_id', '5001')->sole())
        ->phone->toBe('9824017399')->email->toBe('aarti@example.com');
});

it('imports through the dialog and offers the same mapping next time', function () {
    $event = actingAsOrganizer();
    $file = fn (string $rows) => UploadedFile::fake()->createWithContent('export.csv', "Ref,Naam,Sampark,State\n{$rows}");

    Livewire::test(ListAttendees::class)
        ->mountAction('import')
        ->fillForm(['file' => $file("TS-1,Aarti Shah,9824017351,Confirmed\nTS-2,Bina Rao,9824017352,Cancelled\n")])
        ->assertHasNoFormErrors()
        ->fillForm(['map' => ['name' => 'Naam', 'phone' => 'Sampark', 'status' => 'State', 'external_id' => 'Ref'], 'skip' => ['Cancelled']])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified('Import finished');

    expect($event->fresh()->import_mapping)->map->toMatchArray(['name' => 'Naam', 'phone' => 'Sampark', 'status' => 'State', 'external_id' => 'Ref'])
        ->and($event->attendees()->pluck('name')->all())->toBe(['Aarti Shah']);

    // Next export from the same system: nothing to re-map, and Cancelled is still left out.
    Livewire::test(ListAttendees::class)
        ->mountAction('import')
        ->fillForm(['file' => $file("TS-3,Chetan Mehta,9824017353,Confirmed\nTS-4,Dipti Iyer,9824017354,Cancelled\n")])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($event->attendees()->orderBy('name')->pluck('name')->all())->toBe(['Aarti Shah', 'Chetan Mehta'])
        ->and($event->attendees()->where('name', 'Chetan Mehta')->sole()->source)->toBe(AttendeeSource::Import);
});

it('asks for a name column before importing', function () {
    actingAsOrganizer();

    Livewire::test(ListAttendees::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('export.csv', "Naam,Sampark\nAarti Shah,9824017351\n")])
        ->callMountedAction()
        ->assertHasFormErrors(['map.name' => 'required']);
});

it('tells the organizer when imported people can only find their pass by email', function () {
    actingAsOrganizer();

    Livewire::test(ListAttendees::class)
        ->mountAction('import')
        ->fillForm(['file' => UploadedFile::fake()->createWithContent('export.csv', "Name,Email\nAarti Shah,aarti@example.com\n")])
        ->callMountedAction()
        ->assertNotified('1 attendees have an email but no phone');
});

it('hands an imported attendee their pass by email on the registration page', function () {
    $event = Event::factory()->create(['ask_email' => true]);
    AttendeeImporter::import($event, csvFile("Name,Email\nAarti Shah,aarti@example.com\n"));
    $pass = $event->attendees()->sole()->pass;

    $this->post(route('event.register', $event), ['name' => 'Aarti', 'email' => 'Aarti@Example.com'])
        ->assertRedirect(route('pass.show', $pass))
        ->assertSessionHas('existing_pass', true);
    expect($event->attendees()->count())->toBe(1);
});
