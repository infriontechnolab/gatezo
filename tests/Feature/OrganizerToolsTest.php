<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Team;
use App\Filament\Resources\Attendees\Pages\ListAttendees;
use App\Models\Event;
use App\Models\User;
use App\Services\AttendeeImporter;
use App\Services\PassToken;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Tools Fest', 'capacity' => 300, 'allow_reentry' => true]);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);
        $this->event->stalls()->create(['name' => 'Chai Point', 'location' => 'Row A']);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);
    }

    // ---- 1. CSV import --------------------------------------------------------

    public function test_importer_maps_loose_headers_dedupes_by_phone_and_issues_passes(): void
    {
        $csv = "\xEF\xBB\xBFFull Name,Mobile No,E-mail,Ticket,Society Wing\n"
            ."Aarti Shah,+91 98000 00001,aarti@example.com,VIP,B\n"
            ."Bhavesh Patel,098000 00002,,general,C\n"
            .",9800000003,,,\n"
            ."Chirag Mehta,9800000004,not-an-email,,A\n";
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $csv);

        $stats = AttendeeImporter::import($this->event, $path);

        $this->assertSame(['created' => 3, 'updated' => 0, 'skipped' => 1], array_intersect_key($stats, array_flip(['created', 'updated', 'skipped'])));
        $this->assertCount(1, $stats['errors']);

        $aarti = $this->event->attendees()->where('phone', '9800000001')->first();
        $this->assertNotNull($aarti, 'phone normalised from +91 98000 00001');
        $this->assertTrue($aarti->is_vip);
        $this->assertSame('vip', $aarti->ticket_type);
        $this->assertSame('import', $aarti->source);
        $this->assertSame(['Society Wing' => 'B'], $aarti->extra);
        $this->assertNotNull($aarti->pass);

        $this->assertNotNull($this->event->attendees()->where('phone', '9800000002')->first(), 'leading 0 stripped');
        $this->assertNull($this->event->attendees()->where('phone', '9800000004')->value('email'), 'invalid email dropped');

        // Re-import updates instead of duplicating.
        file_put_contents($path, "name,phone\nAarti S.,9800000001\n");
        $again = AttendeeImporter::import($this->event, $path);
        $this->assertSame(1, $again['updated']);
        $this->assertSame(3, $this->event->attendees()->count());
        $this->assertSame('Aarti S.', $aarti->fresh()->name);
    }

    public function test_import_action_on_attendees_page(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('list.csv', "name,phone\nDipti Joshi,9800000009\n");

        Livewire::test(ListAttendees::class)
            ->callAction('import', data: ['file' => $file])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame(1, $this->event->attendees()->where('phone', '9800000009')->count());
    }

    // ---- 6. Revoke / restore --------------------------------------------------

    public function test_revoked_pass_is_refused_at_the_gate_and_on_the_pass_page(): void
    {
        $a = $this->event->attendees()->create(['name' => 'E']);
        $pass = $a->pass()->create(['event_id' => $this->event->id]);

        Livewire::test(ListAttendees::class)->callTableAction('revoke', $a)->assertHasNoTableActionErrors();
        $this->assertTrue($pass->fresh()->revoked);
        $this->get(route('pass.show', $pass))->assertStatus(410);

        Livewire::test(ListAttendees::class)->callTableAction('restore', $a);
        $this->assertFalse($pass->fresh()->revoked);
        $this->get(route('pass.show', $pass))->assertOk();
    }

    public function test_rotate_secret_action_invalidates_existing_tokens(): void
    {
        $a = $this->event->attendees()->create(['name' => 'F']);
        $pass = $a->pass()->create(['event_id' => $this->event->id]);
        $old = PassToken::make($pass);

        Livewire::test(Dashboard::class)->callAction('rotate_secret')->assertNotified();

        $this->assertFalse(PassToken::verify($old, $this->event->fresh()));
    }

    // ---- 2. Vendor link regeneration ------------------------------------------

    public function test_regenerating_vendor_link_kills_the_old_one(): void
    {
        $stall = $this->event->stalls()->first();
        $old = $stall->vendorUrl();
        $this->get($old)->assertOk();

        $stall->regenerateVendorLink();

        $this->get($old)->assertForbidden();
        $this->get($stall->fresh()->vendorUrl())->assertOk();

        // A validly signed URL with the wrong version is also refused.
        $this->get(URL::signedRoute('vendor.show', [$stall, 'v' => 1]))->assertForbidden();
    }

    // ---- 5. Team ----------------------------------------------------------------

    public function test_invite_new_organizer_creates_user_attaches_and_offers_link(): void
    {
        Livewire::test(Team::class)
            ->callAction('invite', data: ['name' => 'Jeet', 'email' => 'Jeet@Example.com'])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $jeet = User::where('email', 'jeet@example.com')->first();
        $this->assertNotNull($jeet);
        $this->assertTrue($jeet->isOrganizerOf($this->event));
        $this->assertTrue($jeet->canAccessTenant($this->event));
    }

    public function test_invite_existing_user_just_attaches_and_remove_detaches(): void
    {
        $existing = User::factory()->create(['email' => 'priya@example.com']);

        Livewire::test(Team::class)->callAction('invite', data: ['name' => 'x', 'email' => 'priya@example.com']);
        $this->assertTrue($existing->isOrganizerOf($this->event));
        $this->assertSame(1, User::where('email', 'priya@example.com')->count());

        Livewire::test(Team::class)->assertSee('priya@example.com')->callTableAction('remove', $existing);
        $this->assertFalse($existing->fresh()->isOrganizerOf($this->event));
    }

    // ---- 7. Duplicate event -----------------------------------------------------

    public function test_duplicate_copies_setup_but_not_people_and_mints_new_secrets(): void
    {
        $this->event->attendees()->create(['name' => 'Old attendee'])->pass()->create(['event_id' => $this->event->id]);
        $this->event->feedback()->create(['rating' => 5]);

        $copy = $this->event->duplicate('Tools Fest 2027', now()->addYear());

        $this->assertNotSame($this->event->slug, $copy->slug);
        $this->assertNotSame($this->event->pass_secret, $copy->pass_secret);
        $this->assertNotSame($this->event->volunteer_code, $copy->volunteer_code);
        $this->assertSame(300, $copy->capacity);
        $this->assertTrue($copy->allow_reentry);
        $this->assertSame(['G1'], $copy->gates()->pluck('code')->all());
        $this->assertSame(['Chai Point'], $copy->stalls()->pluck('name')->all());
        $this->assertSame(0, $copy->attendees()->count());
        $this->assertSame(0, $copy->feedback()->count());
        $this->assertTrue($this->organizer->isOrganizerOf($copy));
    }

    public function test_duplicate_action_redirects_to_the_new_event(): void
    {
        Livewire::test(Dashboard::class)
            ->callAction('duplicate', data: ['name' => 'Copy Fest', 'starts_at' => null, 'ends_at' => null])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $this->assertSame(1, Event::where('name', 'Copy Fest')->count());
    }
}
