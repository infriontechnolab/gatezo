<?php

use App\Enums\TicketType;
use App\Models\Event;
use App\Services\AttendeeImporter;

it('maps known ticket labels and keeps unknown ones in extra', function () {
    $event = Event::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "name,phone,ticket\nAarti Shah,9800000001, VIP \nBina Rao,9800000002,Gold\nChetan Mehta,9800000003,\n");

    $stats = AttendeeImporter::import($event, $path);

    expect($stats['created'])->toBe(3);
    $byName = $event->attendees()->get()->keyBy('name');
    expect($byName['Aarti Shah'])->ticket_type->toBe(TicketType::Vip)->is_vip->toBeTrue()->extra->toBeNull()
        ->and($byName['Bina Rao'])->ticket_type->toBe(TicketType::General)->extra->toBe(['ticket' => 'Gold'])
        ->and($byName['Chetan Mehta'])->ticket_type->toBe(TicketType::General)->extra->toBeNull();
});

it('exports the organizer\'s own ticket label', function () {
    $event = actingAsOrganizer();
    $event->attendees()->create(['name' => 'Bina Rao', 'ticket_type' => TicketType::General, 'extra' => ['ticket' => 'Gold']]);
    $event->attendees()->create(['name' => 'Aarti Shah', 'ticket_type' => TicketType::Vip]);

    $csv = $this->get(route('print.attendees.csv', $event))->assertOk()->streamedContent();

    expect($csv)->toContain('"Bina Rao",,,Gold,')->toContain('"Aarti Shah",,,vip,');
});
