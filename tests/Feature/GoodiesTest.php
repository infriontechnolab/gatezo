<?php

namespace Tests\Feature;

use App\Enums\CheckinDirection;
use App\Enums\HandoutDecision;
use App\Enums\HandoutFlag;
use App\Enums\MemberRole;
use App\Enums\TicketType;
use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Gates\GateResource;
use App\Filament\Resources\Handouts\HandoutResource;
use App\Filament\Widgets\LiveStats;
use App\Models\Event;
use App\Models\Gate;
use App\Models\Pass;
use App\Models\User;
use App\Services\PassToken;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** One goodies kit per attendee, handed out by scanning the pass at a goodies counter. */
class GoodiesTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    private Gate $counter;

    private function setUpEvent(array $goodies = []): void
    {
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Goodies Fest', 'allow_reentry' => true, 'goodies_enabled' => true, 'goodies_name' => 'Welcome kit', ...$goodies]);
        $this->event->members()->attach($this->organizer->id, ['role' => MemberRole::Organizer]);
        $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);
        $this->counter = $this->event->gates()->create(['name' => 'Kit desk', 'code' => 'K1', 'is_goodies' => true]);
        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => 'Ravi']);
    }

    private function pass(string $name, TicketType $ticket = TicketType::General): Pass
    {
        return $this->event->attendees()->create(['name' => $name, 'ticket_type' => $ticket])->pass()->create(['event_id' => $this->event->id]);
    }

    private function checkin(Pass $pass, int $plusSec = 0): array
    {
        return ['client_id' => (string) Str::uuid(), 'token' => PassToken::make($pass), 'gate_id' => null, 'direction' => CheckinDirection::In->value, 'scanned_at' => now()->addSeconds($plusSec)->toIso8601String()];
    }

    private function goodies(Pass $pass, ?string $decision = null, int $plusSec = 60): array
    {
        return array_filter([
            'client_id' => (string) Str::uuid(), 'token' => PassToken::make($pass), 'kind' => 'goodies', 'gate_id' => $this->counter->id,
            'scanned_at' => now()->addSeconds($plusSec)->toIso8601String(), 'decision' => $decision,
        ], fn ($v) => $v !== null);
    }

    public function test_a_second_collection_is_flagged_and_the_bundle_knows_who_collected(): void
    {
        $this->setUpEvent(['goodies_stock' => 10]);
        $pass = $this->pass('Riya');

        $this->getJson(route('scan.bundle'))
            ->assertJsonPath('goodies.name', 'Welcome kit')->assertJsonPath('goodies.left', 10)
            ->assertJsonPath('passes.0.goodies_at', null);

        $this->postJson(route('scan.sync'), ['scans' => [$this->checkin($pass), $this->goodies($pass)]])
            ->assertJsonPath('results.0.status', 'ok')->assertJsonPath('results.1.status', 'ok');

        $this->getJson(route('scan.bundle'))
            ->assertJsonPath('goodies.left', 9)->assertJsonPath('passes.0.goodies_gate', 'Kit desk')
            ->assertJsonPath('passes.0.inside', true); // a handout is not a movement

        // Same pass at the counter again: warned, refused, nothing handed out.
        $this->postJson(route('scan.sync'), ['scans' => [$this->goodies($pass, HandoutDecision::Refused->value, 120), $this->goodies($pass, null, 180), $this->goodies($pass, 'gave_anyway', 240)]])
            ->assertJsonPath('results.0.status', HandoutDecision::Refused->value)
            ->assertJsonPath('results.1.status', HandoutFlag::AlreadyCollected->value)
            ->assertJsonPath('results.2.status', HandoutFlag::AlreadyCollected->value);

        $this->assertSame(4, $this->event->handouts()->count());
        $this->assertSame(3, $this->event->handouts()->given()->count());
        $this->assertSame(2, $this->event->handouts()->given()->where('flag', HandoutFlag::AlreadyCollected)->count());
        $this->assertSame(1, $this->event->checkins()->count());
    }

    public function test_eligibility_warnings_for_not_checked_in_and_ticket_type(): void
    {
        $this->setUpEvent(['goodies_ticket_types' => [TicketType::Vip->value]]);
        $vip = $this->pass('Meera', TicketType::Vip);
        $general = $this->pass('Amit');

        $this->postJson(route('scan.sync'), ['scans' => [
            $this->goodies($vip, null, 0),           // VIP but never came through the gate
            $this->checkin($general, 10),
            $this->goodies($general, HandoutDecision::Refused->value, 20), // phone warned: wrong ticket
        ]])->assertJsonPath('results.0.status', HandoutFlag::NotCheckedIn->value)->assertJsonPath('results.2.status', HandoutDecision::Refused->value);

        $this->assertSame(HandoutFlag::TicketType, $this->event->handouts()->where('decision', HandoutDecision::Refused)->value('flag'));
        $this->getJson(route('scan.bundle'))->assertJsonPath('goodies.ticket_types', [TicketType::Vip->value])->assertJsonPath('goodies.after_checkin', true);
    }

    public function test_replayed_goodies_scans_are_idempotent_and_decisions_do_not_cross_over(): void
    {
        $this->setUpEvent(['goodies_after_checkin' => false]);
        $pass = $this->pass('Kiran');
        $scan = $this->goodies($pass, HandoutDecision::GaveAnyway->value);

        $this->postJson(route('scan.sync'), ['scans' => [$scan]])->assertJsonPath('results.0.status', 'ok');
        $this->postJson(route('scan.sync'), ['scans' => [$scan]])->assertJsonPath('results.0.status', 'already_synced');
        $this->assertNull($this->event->handouts()->value('decision')); // eligible, so not "given anyway"

        // A goodies decision on a gate scan is ignored, not stored on the check-in.
        $this->postJson(route('scan.sync'), ['scans' => [[...$this->checkin($pass), 'decision' => HandoutDecision::GaveAnyway->value]]])->assertJsonPath('results.0.status', 'ok');
        $this->assertNull($this->event->checkins()->value('decision'));
    }

    public function test_bundle_has_no_goodies_when_switched_off_and_a_counter_is_never_an_entry_gate(): void
    {
        $this->setUpEvent(['goodies_enabled' => false]);
        $this->getJson(route('scan.bundle'))->assertJsonPath('goodies', null);

        $this->counter->update(['is_entry' => true]);
        $this->assertFalse($this->counter->fresh()->is_entry);
    }

    public function test_organizer_sees_goodies_in_the_panel_report_and_csv(): void
    {
        $this->setUpEvent(['goodies_stock' => 50]);
        $pass = $this->pass('Riya');
        $this->postJson(route('scan.sync'), ['scans' => [$this->checkin($pass), $this->goodies($pass)]]);

        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        $this->get(Filament::getTenantProfileUrl())->assertOk()->assertSee('Hand out goodies');
        $this->get(GateResource::getUrl('create', tenant: $this->event))->assertOk()->assertSee('Goodies counter');
        Livewire::test(LiveStats::class)->assertSee('Welcome kit given')->assertSee('49 left');
        $this->get(HandoutResource::getUrl('index', tenant: $this->event))->assertOk()->assertSee('Riya');
        $this->get(AttendeeResource::getUrl('index', tenant: $this->event))->assertOk();
        $this->get(route('print.report', $this->event))->assertOk()->assertSee('Kit desk')->assertSee('1 given of 50 in stock');

        $csv = $this->get(route('print.attendees.csv', $this->event))->streamedContent();
        $this->assertStringContainsString('Welcome kit collected at', $csv);
    }
}
