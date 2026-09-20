<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\AttendeeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $event = Event::create(['name' => 'Valid Fest', 'allow_self_register' => true]);
        $event->members()->attach(User::factory()->create()->id, ['role' => 'organizer']);

        return $event;
    }

    public function test_bad_phones_are_rejected_with_a_message_and_good_shapes_pass(): void
    {
        $event = $this->event();
        $post = fn (array $data) => $this->from(route('event.show', $event))->post(route('event.register', $event), $data + ['name' => 'Riya']);

        $post(['phone' => 'abc'])->assertSessionHasErrors(['phone' => 'Enter a phone number using digits only.']);
        $post(['phone' => '12345'])->assertSessionHasErrors('phone');
        $post(['phone' => '9800000001234567'])->assertSessionHasErrors('phone');
        $post(['name' => '', 'phone' => '9800000001'])->assertSessionHasErrors(['name' => 'Tell us your name so the volunteer knows who you are.']);
        $this->assertSame(0, $event->attendees()->count());

        $post(['phone' => '+91 98000 00001'])->assertRedirect()->assertSessionHasNoErrors();
        $post(['name' => 'Sam', 'phone' => '+1 (825) 439-4741'])->assertRedirect()->assertSessionHasNoErrors();
        $post(['name' => 'Nophone', 'phone' => '  '])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(3, $event->attendees()->count());
        $this->assertNull($event->attendees()->where('name', 'Nophone')->value('phone'));
    }

    public function test_csv_import_drops_bad_phone_and_email_but_keeps_the_person(): void
    {
        $event = $this->event();
        $csv = tempnam(sys_get_temp_dir(), 'att');
        file_put_contents($csv, "name,phone,email\nAmit,12,not-an-email\nMeera,+91 98000 00002,meera@example.com\n");

        $stats = AttendeeImporter::import($event, $csv);

        $this->assertSame(2, $stats['created']);
        $this->assertCount(2, $stats['errors']);
        $this->assertStringContainsString('phone "12" ignored', $stats['errors'][0]);
        $this->assertStringContainsString('email "not-an-email" ignored', $stats['errors'][1]);
        $this->assertNull($event->attendees()->where('name', 'Amit')->value('phone'));
        $this->assertSame('9800000002', $event->attendees()->where('name', 'Meera')->value('phone'));
    }
}
