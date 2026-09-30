<?php

use App\Enums\TicketType;
use App\Models\Event;
use App\Services\AttendeeImporter;

function importCsv(Event $event, string $csv): array
{
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, $csv);

    return AttendeeImporter::import($event, $path);
}

it('imports an Eventbrite attendee export and skips refunded orders', function () {
    $event = Event::factory()->create();

    $stats = importCsv($event, <<<'CSV'
        Order #,Order Date,First Name,Last Name,Email,Quantity,Ticket Type,Attendee #,Barcode #,Attendee Status,Cell Phone
        1001,2026-09-20,Aarti,Shah,aarti@example.com,1,VIP,5001,500110001,Attending,+91 98240 17356
        1002,2026-09-21,Bina,Rao,bina@example.com,1,Early bird,5002,500210002,Refunded,9824017357
        1003,2026-09-21,Chetan,Mehta,chetan@example.com,1,General Admission,5003,500310003,Attending,
        CSV);

    expect($stats)->created->toBe(2)->skipped->toBe(1)
        ->and($stats['errors'])->toContain('Line 3: skipped, status "Refunded".');
    $aarti = $event->attendees()->where('name', 'Aarti Shah')->sole();
    expect($aarti)->phone->toBe('9824017356')->email->toBe('aarti@example.com')->ticket_type->toBe(TicketType::Vip)
        ->and($aarti->extra)->toMatchArray(['Barcode #' => '500110001'])
        ->and($aarti->pass)->not->toBeNull();
});

it('imports a Luma guest export and skips declined guests', function () {
    $event = Event::factory()->create();

    $stats = importCsv($event, <<<'CSV'
        api_id,name,first_name,last_name,email,phone_number,created_at,approval_status,ticket_name
        gst-1,Aarti Shah,Aarti,Shah,aarti@example.com,+919824017356,2026-09-20,approved,Standard
        gst-2,Bina Rao,Bina,Rao,bina@example.com,,2026-09-21,declined,Standard
        CSV);

    expect($stats)->created->toBe(1)->skipped->toBe(1)
        ->and($event->attendees()->sole())->name->toBe('Aarti Shah')->phone->toBe('9824017356');
});

it('reads Google Forms question headers', function () {
    $event = Event::factory()->create();

    $stats = importCsv($event, <<<'CSV'
        Timestamp,Email Address,Your full name,College name,WhatsApp number (for your pass),Parent's phone number
        2026/09/20 10:00:00 AM,aarti@example.com,Aarti Shah,LD College,98240 17356,9824017399
        CSV);

    expect($stats['created'])->toBe(1);
    expect($event->attendees()->sole())
        ->name->toBe('Aarti Shah')
        ->phone->toBe('9824017356')
        ->email->toBe('aarti@example.com')
        ->extra->toMatchArray(['College name' => 'LD College', "Parent's phone number" => '9824017399']);
});
