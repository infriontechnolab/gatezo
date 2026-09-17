<?php

namespace Tests\Feature;

use App\Http\Controllers\ScannerController;
use App\Models\Event;
use App\Models\User;
use App\Services\PassToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFlowTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        $organizer = User::factory()->create();
        $event = Event::create(['name' => 'Test Fest', 'allow_reentry' => true] + $attrs);
        $event->members()->attach($organizer->id, ['role' => 'organizer']);
        $event->gates()->create(['name' => 'Main', 'code' => 'G1']);
        $event->gates()->create(['name' => 'Side', 'code' => 'G2']);

        return $event;
    }

    public function test_walkup_registration_issues_a_pass_and_reuses_it_for_same_phone(): void
    {
        $event = $this->event();

        $first = $this->post(route('event.register', $event), ['name' => 'Aarti', 'phone' => '9800000001']);
        $first->assertRedirect();
        $passUrl = $first->headers->get('Location');

        $this->get($passUrl)->assertOk()->assertSee('Aarti')->assertSee('<svg', false);

        $second = $this->post(route('event.register', $event), ['name' => 'Aarti again', 'phone' => '9800000001']);
        $this->assertSame($passUrl, $second->headers->get('Location'));
        $this->assertSame(1, $event->attendees()->count());
    }

    public function test_registration_closed_when_self_register_disabled(): void
    {
        $event = $this->event(['allow_self_register' => false]);

        $this->post(route('event.register', $event), ['name' => 'X'])->assertForbidden();
    }

    public function test_pass_token_round_trips_and_rotating_secret_invalidates(): void
    {
        $event = $this->event();
        $attendee = $event->attendees()->create(['name' => 'B']);
        $pass = $attendee->pass()->create(['event_id' => $event->id]);

        $token = PassToken::make($pass);
        $this->assertTrue(PassToken::verify($token, $event));
        $this->assertFalse(PassToken::verify('EQ1.'.$pass->code.'.0000000000000000', $event));

        $event->rotatePassSecret();
        $this->assertFalse(PassToken::verify($token, $event->fresh()));
    }

    public function test_volunteer_joins_with_code_and_syncs_scans_idempotently_with_duplicate_flag(): void
    {
        $event = $this->event();
        $attendee = $event->attendees()->create(['name' => 'C']);
        $pass = $attendee->pass()->create(['event_id' => $event->id]);
        $token = PassToken::make($pass);
        [$g1, $g2] = $event->gates()->pluck('id');

        $this->post(route('scan.join.post'), ['code' => '000000', 'name' => 'Ravi'])->assertSessionHasErrors('code');
        $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi'])->assertRedirect(route('scan.app'));

        $this->get(route('scan.app'))->assertOk();
        $this->getJson(route('scan.bundle'))
            ->assertOk()
            ->assertJsonPath('event.slug', $event->slug)
            ->assertJsonPath('passes.0.code', $pass->code)
            ->assertJsonCount(2, 'gates');

        $now = now()->toIso8601String();
        $scan = fn (string $id, int $gate, ?string $tok = null) => [
            'client_id' => $id, 'token' => $tok ?? $token, 'gate_id' => $gate, 'direction' => 'in', 'scanned_at' => $now,
        ];
        $a = '11111111-1111-4111-8111-111111111111';
        $b = '22222222-2222-4222-8222-222222222222';
        $c = '33333333-3333-4333-8333-333333333333';

        $this->postJson(route('scan.sync'), ['scans' => [$scan($a, $g1)]])
            ->assertOk()->assertJsonPath('results.0.status', 'ok');

        $this->postJson(route('scan.sync'), ['scans' => [
            $scan($a, $g1),                                   // replay
            $scan($b, $g2),                                   // same pass, other gate → duplicate
            $scan($c, $g2, 'EQ1.'.$pass->code.'.deadbeefdeadbeef'), // forged
        ]])->assertOk()->assertJson(['results' => [
            ['client_id' => $a, 'status' => 'already_synced'],
            ['client_id' => $b, 'status' => 'duplicate'],
            ['client_id' => $c, 'status' => 'invalid_signature'],
        ]]);

        $this->assertSame(2, $event->checkins()->count());
        $this->assertSame(1, $event->checkins()->where('duplicate_flag', true)->count());

        $this->postJson(route('scan.duty'), ['gate_id' => $g1, 'status' => 'on'])->assertOk();
        $this->assertSame(1, $event->dutyLogs()->count());
    }

    public function test_same_name_on_two_phones_is_two_volunteers_but_rejoin_from_one_phone_is_the_same(): void
    {
        $event = $this->event();
        $join = fn () => $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

        // Phone A joins; gets a device cookie.
        $a = $join();
        $cookieA = collect($a->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === ScannerController::DEVICE_COOKIE);
        $this->assertNotNull($cookieA);
        $this->assertSame(1, $event->members()->wherePivot('role', 'volunteer')->count());

        // Phone B (no cookie) joins with the same name → a second volunteer.
        $this->flushSession();
        $join();
        $this->assertSame(2, $event->members()->wherePivot('role', 'volunteer')->count());

        // Phone A's session expired; it rejoins with its cookie → still 2, not 3.
        $this->flushSession();
        $this->withUnencryptedCookie(ScannerController::DEVICE_COOKIE, $cookieA->getValue());
        $join();
        $this->assertSame(2, $event->members()->wherePivot('role', 'volunteer')->count());
    }

    public function test_expired_volunteer_session_returns_401_json_so_the_scanner_keeps_its_queue(): void
    {
        $event = $this->event();
        $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);
        $this->flushSession(); // simulates SESSION_LIFETIME running out

        $this->postJson(route('scan.sync'), ['scans' => []])->assertUnauthorized();
    }

    public function test_scanner_routes_require_volunteer_session(): void
    {
        $this->get(route('scan.app'))->assertRedirect(route('scan.join'));
        $this->getJson(route('scan.bundle'))->assertUnauthorized();
    }

    public function test_feedback_is_anonymous_by_default_and_one_per_named_attendee(): void
    {
        $event = $this->event();
        $attendee = $event->attendees()->create(['name' => 'D']);
        $pass = $attendee->pass()->create(['event_id' => $event->id]);

        $this->post(route('event.feedback', $event), ['rating' => 4])->assertOk();
        $this->post(route('event.feedback', $event), ['rating' => 2])->assertOk();
        $this->post(route('event.feedback', $event), ['rating' => 5, 'pass_code' => $pass->code])->assertOk();
        $this->post(route('event.feedback', $event), ['rating' => 1, 'pass_code' => $pass->code])->assertOk();

        $this->assertSame(3, $event->feedback()->count());
        $this->assertSame(1, $event->feedback()->where('attendee_id', $attendee->id)->value('rating'));
    }

    public function test_stall_page_counts_views(): void
    {
        $event = $this->event();
        $stall = $event->stalls()->create(['name' => 'Chai Point', 'products' => [['name' => 'Chai', 'price' => 20]]]);

        $this->get(route('stall.show', $stall))->assertOk()->assertSee('Chai Point')->assertSee('₹20');
        $this->assertSame(1, $stall->fresh()->view_count);
    }

    public function test_manifest_is_per_surface_and_pass_page_links_its_own(): void
    {
        $event = $this->event();
        $pass = $event->attendees()->create(['name' => 'P'])->pass()->create(['event_id' => $event->id]);

        $this->get(route('manifest', ['start' => '/pass/'.$pass->code, 'event' => $event->slug]))
            ->assertOk()->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('start_url', '/pass/'.$pass->code)->assertJsonPath('name', 'Test Fest pass')
            ->assertJsonPath('icons.3.purpose', 'maskable');
        $this->get(route('manifest'))->assertJsonPath('start_url', '/scan')->assertJsonPath('name', 'Gatezo scanner');
        $this->get(route('manifest', ['start' => 'https://evil.com/x']))->assertJsonPath('start_url', '/scan');

        $this->get(route('pass.show', $pass))->assertOk()
            ->assertSee('manifest.webmanifest?start=%2Fpass%2F'.$pass->code, false)
            ->assertSee('apple-touch-icon', false);
        $this->get(route('scan.join'))->assertSee('manifest.webmanifest?start=%2Fscan', false);
    }

    public function test_landing_page_renders_with_real_qr_codes_and_ctas(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Replace the clipboard with a QR code.')
            ->assertSee('/admin/register', false)
            ->assertSee('href="#how"', false)
            ->assertSee('<svg', false)
            ->assertSee('landing/dashboard.png', false);
    }
}
