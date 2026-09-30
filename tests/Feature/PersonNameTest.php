<?php

namespace Tests\Feature;

use App\Enums\AttendeeSource;
use App\Models\Event;
use App\Rules\PersonName;
use App\Services\AttendeeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Junk like "%%%$$$$" never becomes an attendee; real names in any script do. */
class PersonNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_what_counts_as_a_name(): void
    {
        foreach (['Aarti Shah', 'R. K. D\'Souza-Shah', 'આરતી શાહ', 'प्रिया वर्मा', 'Jo', '  Hetal  '] as $ok) {
            $this->assertTrue(PersonName::passes($ok), $ok);
        }
        foreach (['%%%$$$$', '123', '.', 'A', 'Riya@123', '<script>', '---', 'Amit 2'] as $bad) {
            $this->assertFalse(PersonName::passes($bad), $bad);
        }
    }

    public function test_public_registration_refuses_junk_names(): void
    {
        $event = Event::create(['name' => 'Property Expo', 'allow_self_register' => true]);

        $this->post(route('event.register', $event), ['name' => '%%%$$$$', 'phone' => '9823456710'])
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput(['name' => '%%%$$$$', 'phone' => '9823456710']);
        $this->assertSame(0, $event->attendees()->count());

        $this->post(route('event.register', $event), ['name' => 'આરતી શાહ', 'phone' => '9823456710'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $event->attendees()->count());
    }

    public function test_csv_import_skips_junk_names_and_says_which_line(): void
    {
        $event = Event::create(['name' => 'Property Expo']);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "name,phone\nAarti Shah,9800000001\n%%%\$\$\$\$,9800000002\n");

        $stats = AttendeeImporter::import($event, $path);

        $this->assertSame(1, $stats['created']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertStringContainsString('Line 3', implode(' ', $stats['errors']));
        $this->assertSame(['Aarti Shah'], $event->attendees()->pluck('name')->all());
    }

    public function test_junk_names_command_lists_only_bad_names(): void
    {
        $expo = Event::create(['name' => 'Property Expo']);
        $fair = Event::create(['name' => 'Society Fair']);
        $expo->attendees()->createMany([['name' => 'Aarti Shah'], ['name' => '%%%$$$$', 'source' => AttendeeSource::Online]]);
        $fair->attendees()->create(['name' => '123']);

        $this->artisan('gatezo:junk-names')
            ->expectsOutputToContain('"%%%$$$$"')->expectsOutputToContain('"123"')
            ->expectsOutputToContain('2 attendees to review')
            ->doesntExpectOutputToContain('Aarti Shah')
            ->assertSuccessful();

        $this->artisan('gatezo:junk-names', ['--event' => $fair->slug])->expectsOutputToContain('1 attendee to review')->assertSuccessful();
        $this->artisan('gatezo:junk-names', ['--event' => 'nope'])->assertFailed();
        $fair->attendees()->delete();
        $this->artisan('gatezo:junk-names', ['--event' => $fair->slug])->expectsOutputToContain('No junk names found')->assertSuccessful();
    }
}
