<?php

namespace Tests\Feature;

use App\Filament\Pages\PrintCentre;
use App\Filament\Widgets\ArrivalsChart;
use App\Filament\Widgets\FeedbackChart;
use App\Filament\Widgets\OnDutyBoard;
use App\Models\Event;
use App\Models\User;
use App\Services\PassToken;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QrFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'QR Fest', 'capacity' => 500]);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);
        $this->event->gates()->create(['name' => 'Food Court', 'code' => 'Z1', 'is_entry' => false]);
        $this->event->stalls()->create(['name' => 'Chai Point', 'location' => 'Row A']);
    }

    // ---- print kit / report / csv ------------------------------------------

    public function test_print_kit_has_a_qr_for_everything(): void
    {
        $this->actingAs($this->organizer)->get(route('print.kit', $this->event))
            ->assertOk()
            ->assertSee('Scan to get your entry pass')
            ->assertSee('Main Gate')->assertSee('G1')
            ->assertSee('Food Court')->assertSee('Duty zone')
            ->assertSee('Chai Point')
            ->assertSee('How was it?')
            ->assertSee(route('event.show', $this->event))
            ->assertSee(route('scan.join'));

        $svgs = substr_count($this->actingAs($this->organizer)->get(route('print.kit', $this->event))->getContent(), '<svg');
        $this->assertSame(1 + 2 + 1 + 2, $svgs); // poster + 2 gates + 1 stall + 2 feedback cards
    }

    public function test_print_pages_are_organizer_only(): void
    {
        $this->get(route('print.kit', $this->event))->assertRedirect(route('filament.admin.auth.login'));
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('print.kit', $this->event))->assertForbidden();
        $this->actingAs($stranger)->get(route('print.report', $this->event))->assertForbidden();
        $this->actingAs($stranger)->get(route('print.attendees.csv', $this->event))->assertForbidden();
    }

    public function test_report_and_csv_reflect_checkins_and_feedback(): void
    {
        $gate = $this->event->gates()->first();
        foreach (['Aarti', 'Bhavesh', 'Chirag'] as $name) {
            $a = $this->event->attendees()->create(['name' => $name, 'phone' => '9'.rand(100000000, 999999999)]);
            $p = $a->pass()->create(['event_id' => $this->event->id]);
            if ($name !== 'Chirag') {
                $p->checkins()->create(['event_id' => $this->event->id, 'gate_id' => $gate->id, 'scanned_at' => now()->setTime(19, 40), 'client_id' => (string) Str::uuid()]);
            }
        }
        $this->event->feedback()->create(['rating' => 5, 'comment' => 'Loved the dandiya']);
        $this->event->feedback()->create(['rating' => 3]);

        $this->actingAs($this->organizer)->get(route('print.report', $this->event))
            ->assertOk()
            ->assertSee('attended (unique)')
            ->assertSee('7:30 PM')          // peak bucket
            ->assertSee('4 ★')              // average of 5 and 3
            ->assertSee('Loved the dandiya')
            ->assertSee('Chai Point');

        $csv = $this->actingAs($this->organizer)->get(route('print.attendees.csv', $this->event));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Name,Phone,Email,Ticket,VIP,Source,Pass,"Checked in at","Registered at"', $body);
        $this->assertSame(4, substr_count($body, "\n")); // header + 3 rows
        $this->assertStringContainsString('Aarti', $body);
    }

    public function test_print_centre_page_and_new_widgets_render(): void
    {
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        $this->get(PrintCentre::getUrl(tenant: $this->event))->assertOk()->assertSee('Open print kit');

        $volunteer = User::factory()->create(['name' => 'Ravi', 'email' => 'ravi.'.$this->event->id.'@'.User::VOLUNTEER_DOMAIN]);
        $this->event->dutyLogs()->create(['volunteer_id' => $volunteer->id, 'gate_id' => $this->event->gates()->first()->id, 'status' => 'on', 'at' => now()]);
        $this->event->feedback()->create(['rating' => 4]);

        Livewire::test(OnDutyBoard::class)->assertSee('Ravi')->assertSee('Main Gate')->assertSee('On duty');
        Livewire::test(FeedbackChart::class)->assertSee('4.0');
        Livewire::test(ArrivalsChart::class)->assertOk();
    }

    // ---- gate signs → on duty ------------------------------------------------

    public function test_gate_sign_scanned_with_camera_app_before_joining_records_duty_after_join(): void
    {
        $gate = $this->event->gates()->first();

        $this->get($gate->signUrl())->assertRedirect(route('scan.join'))->assertSessionHas('hint');
        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => 'Ravi'])->assertRedirect(route('scan.app'));

        $this->assertSame(1, $this->event->dutyLogs()->where('gate_id', $gate->id)->where('status', 'on')->count());
    }

    public function test_gate_sign_scanned_after_joining_records_duty_immediately(): void
    {
        $gate = $this->event->gates()->where('code', 'Z1')->first();
        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => 'Ravi']);

        $this->get($gate->signUrl())->assertRedirect(route('scan.app'))->assertSessionHas('duty', 'Food Court');
        $this->assertSame(1, $this->event->dutyLogs()->where('gate_id', $gate->id)->count());
    }

    // ---- vendor lead capture --------------------------------------------------

    public function test_vendor_link_must_be_signed(): void
    {
        $stall = $this->event->stalls()->first();

        $this->get(route('vendor.show', $stall))->assertForbidden();
        $this->get($stall->vendorUrl())->assertOk()->assertSee('Chai Point')->assertSee('Scan an attendee');
    }

    public function test_vendor_captures_lead_only_for_opted_in_attendees(): void
    {
        $stall = $this->event->stalls()->first();
        $attendee = $this->event->attendees()->create(['name' => 'Dipti', 'phone' => '9800000009']);
        $pass = $attendee->pass()->create(['event_id' => $this->event->id]);
        $token = PassToken::make($pass);
        $leadUrl = URL::signedRoute('vendor.lead', [$stall, 'v' => $stall->link_version]);

        // Not opted in → refused, name returned so the vendor can ask them.
        $this->postJson($leadUrl, ['token' => $token])->assertStatus(403)->assertJson(['status' => 'not_opted_in', 'name' => 'Dipti']);
        $this->assertSame(0, $stall->leads()->count());

        // Attendee flips the toggle on their pass page.
        $this->post(route('pass.consent', $pass), ['share_contact' => 1])->assertRedirect();
        $this->assertTrue($attendee->fresh()->share_contact);

        $this->postJson($leadUrl, ['token' => $token])->assertOk()->assertJson(['status' => 'ok', 'lead' => ['name' => 'Dipti', 'phone' => '9800000009']]);
        $this->postJson($leadUrl, ['token' => $token])->assertOk()->assertJsonPath('status', 'already_captured');
        $this->assertSame(1, $stall->leads()->count());

        // Forged token, unknown pass.
        $this->postJson($leadUrl, ['token' => 'EQ1.'.$pass->code.'.0000000000000000'])->assertStatus(422);

        // Vendor bundle carries the opt-in flag so the scanner can warn offline.
        $this->getJson(URL::signedRoute('vendor.bundle', [$stall, 'v' => $stall->link_version]))->assertOk()->assertJsonPath('passes.0.opted_in', true);

        // CSV
        $csv = $this->get(URL::signedRoute('vendor.leads.csv', [$stall, 'v' => $stall->link_version]));
        $csv->assertOk();
        $this->assertStringContainsString('Dipti,9800000009', $csv->streamedContent());
    }

    public function test_pass_page_shows_consent_toggle_only_when_event_has_stalls(): void
    {
        $attendee = $this->event->attendees()->create(['name' => 'E']);
        $pass = $attendee->pass()->create(['event_id' => $this->event->id]);
        $this->get(route('pass.show', $pass))->assertOk()->assertSee('Allow stalls to contact me');

        $this->event->stalls()->delete();
        $this->get(route('pass.show', $pass))->assertOk()->assertDontSee('Allow stalls to contact me');
    }

    // ---- gate board -------------------------------------------------------------

    public function test_gate_board_is_signed_public_and_counts_people_inside(): void
    {
        $this->get(route('board.show', $this->event))->assertForbidden(); // unsigned

        $gate = $this->event->gates()->first();
        foreach (['A', 'B'] as $n) {
            $p = $this->event->attendees()->create(['name' => $n])->pass()->create(['event_id' => $this->event->id]);
            $p->checkins()->create(['event_id' => $this->event->id, 'gate_id' => $gate->id, 'scanned_at' => now(), 'client_id' => (string) Str::uuid()]);
        }
        // B leaves again.
        $this->event->passes()->latest('id')->first()->checkins()->create(['event_id' => $this->event->event_id ?? $this->event->id, 'gate_id' => $gate->id, 'direction' => 'out', 'scanned_at' => now(), 'client_id' => (string) Str::uuid()]);

        $this->get($this->event->boardUrl())->assertOk()->assertSee('Inside now')->assertSee('QR Fest');

        $this->getJson(URL::signedRoute('board.json', $this->event))
            ->assertOk()
            ->assertJsonPath('inside', 1)
            ->assertJsonPath('checked_in', 2)
            ->assertJsonPath('capacity', 500)
            ->assertJsonPath('level', 'ok')
            ->assertJsonPath('gates.0.code', 'G1')
            ->assertJsonPath('gates.0.ins', 2);
    }
}
