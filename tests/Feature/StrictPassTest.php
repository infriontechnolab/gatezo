<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\PassToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Strict passes: the QR rotates every 30 s so a forwarded screenshot dies within a minute. */
class StrictPassTest extends TestCase
{
    use RefreshDatabase;

    private function event(bool $strict = true): Event
    {
        $organizer = User::factory()->create();
        $event = Event::create(['name' => 'Strict Fest', 'allow_reentry' => true, 'strict_passes' => $strict]);
        $event->members()->attach($organizer->id, ['role' => 'organizer']);
        $event->gates()->create(['name' => 'Main', 'code' => 'G1']);

        return $event;
    }

    public function test_rotating_token_verifies_inside_the_window_and_static_is_refused_in_strict_mode(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Riya'])->pass()->create(['event_id' => $event->id]);
        $now = 1_800_000_000;

        $token = PassToken::rotating($pass, $event, PassToken::slot($now));
        $this->assertStringStartsWith('EQ2.'.$pass->code.'.', $token);
        $this->assertTrue(PassToken::verify($token, $event, $now));
        $this->assertTrue(PassToken::verify($token, $event, $now + 45), 'one slot of skew is fine');
        $this->assertFalse(PassToken::verify($token, $event, $now + 90), 'two slots later it is a screenshot');
        $this->assertSame('expired_pass', PassToken::failure(PassToken::rotating($pass, $event, PassToken::slot() - 5), $event));

        $static = PassToken::make($pass, $event);
        $this->assertFalse(PassToken::verify($static, $event));
        $this->assertSame('static_pass', PassToken::failure($static, $event));

        // Tampered mac
        $this->assertFalse(PassToken::verify(substr($token, 0, -1).'0', $event, $now));

        // Non-strict events accept both.
        $event->update(['strict_passes' => false]);
        $event->refresh();
        $this->assertTrue(PassToken::verify($static, $event));
        $this->assertTrue(PassToken::verify(PassToken::rotating($pass, $event), $event));
        $this->assertStringStartsWith('EQ1.', PassToken::current($pass, $event));
    }

    public function test_pass_page_is_live_and_serves_fresh_qr(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Amit'])->pass()->create(['event_id' => $event->id]);

        $this->get(route('pass.show', $pass))->assertOk()->assertSee('Live pass')->assertSee('livePass(', false)->assertSee($pass->code.'\/qr', false)->assertSee('Screenshots stop working');
        $this->getJson(route('pass.qr', $pass))->assertOk()->assertJsonStructure(['svg', 'seconds_left']);

        $event->update(['strict_passes' => false]);
        $this->get(route('pass.show', $pass))->assertOk()->assertDontSee('Live pass');
        $this->getJson(route('pass.qr', $pass))->assertNotFound();
    }

    public function test_scanner_sync_reports_static_and_expired_passes_and_accepts_live_ones(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'Meera'])->pass()->create(['event_id' => $event->id]);
        $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);
        $this->getJson(route('scan.bundle'))->assertJsonPath('event.strict_passes', true);

        $scan = fn (string $token, int $at) => ['client_id' => (string) Str::uuid(), 'token' => $token, 'gate_id' => null, 'direction' => 'in', 'scanned_at' => date(DATE_ATOM, $at)];
        $t = time();

        $this->postJson(route('scan.sync'), ['scans' => [
            $scan(PassToken::make($pass, $event), $t),
            $scan(PassToken::rotating($pass, $event, PassToken::slot($t) - 4), $t),
            $scan(PassToken::rotating($pass, $event, PassToken::slot($t)), $t),
        ]])->assertOk()
            ->assertJsonPath('results.0.status', 'static_pass')
            ->assertJsonPath('results.1.status', 'expired_pass')
            ->assertJsonPath('results.2.status', 'ok');

        // Offline queue replayed later: verified against the time of the scan, not the sync.
        $old = $t - 3600;
        $this->postJson(route('scan.sync'), ['scans' => [$scan(PassToken::rotating($pass, $event, PassToken::slot($old)), $old)]])
            ->assertJsonPath('results.0.status', 'duplicate'); // genuine token for that moment; already inside now
    }
}
