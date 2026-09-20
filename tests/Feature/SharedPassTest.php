<?php

namespace Tests\Feature;

use App\Http\Controllers\BoardController;
use App\Models\Event;
use App\Models\User;
use App\Services\PassToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** A pass forwarded on WhatsApp must not admit two people without a volunteer choosing to. */
class SharedPassTest extends TestCase
{
    use RefreshDatabase;

    private function event(bool $reentry = true): Event
    {
        $organizer = User::factory()->create();
        $event = Event::create(['name' => 'Shared Fest', 'allow_reentry' => $reentry]);
        $event->members()->attach($organizer->id, ['role' => 'organizer']);
        $event->gates()->create(['name' => 'Main', 'code' => 'G1']);
        $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

        return $event;
    }

    private function scan(string $token, string $dir = 'in', ?string $decision = null, int $plusSec = 0): array
    {
        return array_filter([
            'client_id' => (string) Str::uuid(), 'token' => $token, 'gate_id' => null,
            'direction' => $dir, 'scanned_at' => now()->addSeconds($plusSec)->toIso8601String(), 'decision' => $decision,
        ], fn ($v) => $v !== null);
    }

    public function test_second_entry_on_a_forwarded_pass_is_a_duplicate_and_the_bundle_says_inside(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Riya'])->pass()->create(['event_id' => $event->id]);
        $token = PassToken::make($pass);

        $this->getJson(route('scan.bundle'))->assertJsonPath('passes.0.inside', false)->assertJsonPath('passes.0.entered', false);

        $this->postJson(route('scan.sync'), ['scans' => [$this->scan($token)]])->assertJsonPath('results.0.status', 'ok');
        $this->getJson(route('scan.bundle'))->assertJsonPath('passes.0.inside', true)->assertJsonPath('passes.0.entered', true)->assertJsonPath('passes.0.last_gate', null);

        // Same QR, second phone, an hour later: still a duplicate (the old 10-minute window is gone).
        $this->postJson(route('scan.sync'), ['scans' => [$this->scan($token, 'in', null, 3600)]])->assertJsonPath('results.0.status', 'duplicate');

        // Out, then in again: fine, re-entry is on.
        $this->postJson(route('scan.sync'), ['scans' => [$this->scan($token, 'out', null, 3700), $this->scan($token, 'in', null, 3800)]])
            ->assertJsonPath('results.0.status', 'ok')->assertJsonPath('results.1.status', 'ok');
    }

    public function test_no_reentry_events_treat_any_second_entry_as_duplicate(): void
    {
        $event = $this->event(reentry: false);
        $pass = $event->attendees()->create(['name' => 'Amit'])->pass()->create(['event_id' => $event->id]);
        $token = PassToken::make($pass);

        $this->postJson(route('scan.sync'), ['scans' => [
            $this->scan($token), $this->scan($token, 'out', null, 60), $this->scan($token, 'in', null, 120),
        ]])->assertJsonPath('results.0.status', 'ok')->assertJsonPath('results.1.status', 'ok')->assertJsonPath('results.2.status', 'duplicate');
    }

    public function test_turned_away_is_recorded_but_is_not_an_entry_and_let_in_is_flagged(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Meera'])->pass()->create(['event_id' => $event->id]);
        $token = PassToken::make($pass);

        $this->postJson(route('scan.sync'), ['scans' => [
            $this->scan($token),
            $this->scan($token, 'in', 'turned_away', 30),
            $this->scan($token, 'in', 'let_in', 60),
        ]])->assertJsonPath('results.0.status', 'ok')->assertJsonPath('results.1.status', 'turned_away')->assertJsonPath('results.2.status', 'duplicate');

        $this->assertSame(3, $event->checkins()->count());
        $this->assertSame(1, $event->checkins()->where('direction', 'denied')->where('decision', 'turned_away')->count());
        $this->assertSame(1, $event->checkins()->where('decision', 'let_in')->where('duplicate_flag', true)->count());

        // One person inside, two entry scans counted as "in"; the denied row counts for neither.
        $stats = BoardController::stats($event);
        $this->assertSame(1, $stats['inside']);
        $this->assertSame(2, $event->checkins()->where('direction', 'in')->count());

        // A decision sent for a scan the server does not consider a duplicate is dropped.
        $out = $this->scan($token, 'out', 'let_in', 90);
        $this->postJson(route('scan.sync'), ['scans' => [$out]])->assertJsonPath('results.0.status', 'ok');
        $this->assertNull($event->checkins()->where('client_id', $out['client_id'])->value('decision'));
    }

    public function test_exit_scan_for_someone_not_inside_is_a_duplicate(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Jay'])->pass()->create(['event_id' => $event->id]);
        $this->postJson(route('scan.sync'), ['scans' => [$this->scan(PassToken::make($pass), 'out')]])->assertJsonPath('results.0.status', 'duplicate');
    }
}
