<?php

namespace Tests\Feature;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Checkins\CheckinResource;
use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\Gates\GateResource;
use App\Filament\Resources\Stalls\StallResource;
use App\Filament\Widgets\GateStats;
use App\Filament\Widgets\LiveStats;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Panel Fest', 'capacity' => 100]);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);

        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);
    }

    public function test_dashboard_renders_and_live_widgets_compute(): void
    {
        $this->get(Filament::getUrl($this->event))->assertOk();

        $attendee = $this->event->attendees()->create(['name' => 'A']);
        $pass = $attendee->pass()->create(['event_id' => $this->event->id]);
        $pass->checkins()->create([
            'event_id' => $this->event->id,
            'gate_id' => $this->event->gates()->first()->id,
            'scanned_at' => now(),
            'client_id' => (string) Str::uuid(),
        ]);

        // Widgets are lazy-loaded by Livewire, so render them directly.
        Livewire::test(LiveStats::class)->assertSee('Inside now')->assertSee('1% of 100');
        Livewire::test(GateStats::class)->assertSee('Main')->assertSee('G1');
    }

    public function test_resource_index_pages_render_for_tenant(): void
    {
        foreach ([GateResource::class, AttendeeResource::class, StallResource::class, FeedbackResource::class, CheckinResource::class] as $resource) {
            $this->get($resource::getUrl('index', tenant: $this->event))->assertOk();
        }
    }

    public function test_organizer_cannot_open_someone_elses_event(): void
    {
        $other = Event::create(['name' => 'Not Mine']);

        $this->get(GateResource::getUrl('index', tenant: $other))->assertNotFound();
    }

    public function test_volunteer_accounts_cannot_access_panel(): void
    {
        $volunteer = User::factory()->create(['email' => 'ravi.1@'.User::VOLUNTEER_DOMAIN]);

        $this->actingAs($volunteer)->get('/admin')->assertForbidden();
    }
}
