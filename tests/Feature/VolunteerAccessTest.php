<?php

namespace Tests\Feature;

use App\Filament\Pages\Volunteers;
use App\Http\Controllers\ScannerController;
use App\Models\Event;
use App\Models\User;
use App\Models\VolunteerJoin;
use App\Services\PassToken;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VolunteerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Access Fest']);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);
    }

    private function join(string $name = 'Ravi', ?string $code = null)
    {
        return $this->post(route('scan.join.post'), ['code' => $code ?? $this->event->volunteer_code, 'name' => $name]);
    }

    // 1. no secret on the phone
    public function test_bundle_carries_per_pass_signatures_not_the_event_secret(): void
    {
        $pass = $this->event->attendees()->create(['name' => 'A'])->pass()->create(['event_id' => $this->event->id]);
        $this->join();

        $json = $this->getJson(route('scan.bundle'))->assertOk()->json();
        $this->assertArrayNotHasKey('pass_secret', $json);
        $this->assertSame(PassToken::sign($pass->code, $this->event->pass_secret), $json['passes'][0]['sig']);
        $this->assertStringNotContainsString($this->event->pass_secret, json_encode($json));
    }

    // 2. rotate code
    public function test_rotating_the_code_ends_every_session_and_old_code_stops_working(): void
    {
        $old = $this->event->volunteer_code;
        $this->join();
        $this->getJson(route('scan.bundle'))->assertOk();

        $this->event->rotateVolunteerCode();
        $this->assertNotSame($old, $this->event->fresh()->volunteer_code);

        $this->getJson(route('scan.bundle'))->assertUnauthorized(); // queue kept client-side, banner asks to rejoin
        $this->flushSession();
        $this->join('Ravi', $old)->assertSessionHasErrors('code');
        $this->join('Ravi', $this->event->fresh()->volunteer_code)->assertRedirect(route('scan.app'));
    }

    // 3. kick
    public function test_kicked_volunteer_loses_access_and_cannot_rejoin_until_restored(): void
    {
        $first = $this->join();
        // Same phone = same device cookie on every later join.
        $cookie = collect($first->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === ScannerController::DEVICE_COOKIE)->getValue();
        $this->withUnencryptedCookie(ScannerController::DEVICE_COOKIE, $cookie);
        $ravi = User::where('email', 'like', 'ravi.%')->first();
        $this->getJson(route('scan.bundle'))->assertOk();

        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);
        Livewire::test(Volunteers::class)->assertSee('Ravi')->callTableAction('kick', $ravi)->assertNotified();
        auth()->logout();

        $this->getJson(route('scan.bundle'))->assertUnauthorized();
        $this->flushSession();
        $this->join()->assertSessionHasErrors('code');
        $this->assertSame('kicked', VolunteerJoin::latest('id')->value('result'));

        $this->actingAs($this->organizer);
        Livewire::test(Volunteers::class)->callTableAction('restore', $ravi);
        auth()->logout();
        $this->flushSession();
        $this->join()->assertRedirect(route('scan.app'));
    }

    // 4. roster-only
    public function test_roster_only_mode_admits_only_rostered_names(): void
    {
        $this->event->update(['roster_only' => true]);
        $this->event->shifts()->create(['volunteer_name' => 'Priya Dave', 'gate_id' => $this->event->gates()->first()->id, 'starts_at' => now(), 'ends_at' => now()->addHour()]);

        $this->join('Random Person')->assertSessionHasErrors('name');
        $this->assertSame('not_on_roster', VolunteerJoin::latest('id')->value('result'));
        $this->flushSession();
        $this->join('priya dave')->assertRedirect(route('scan.app'));
    }

    // 5. approval mode
    public function test_approval_mode_holds_new_volunteers_until_an_organizer_approves(): void
    {
        $this->event->update(['require_volunteer_approval' => true]);
        $this->join();

        $this->get(route('scan.app'))->assertOk()->assertSee('Almost in')->assertDontSee('Type pass code');
        $this->getJson(route('scan.bundle'))->assertForbidden()->assertJsonPath('pending', true);
        $this->postJson(route('scan.claim'), ['token' => 'EQ1.X.Y'])->assertForbidden(); // 7. no draw claims while pending

        $ravi = User::where('email', 'like', 'ravi.%')->first();
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);
        Livewire::test(Volunteers::class)->assertSee('waiting')->callTableAction('approve', $ravi)->assertNotified();
        auth()->logout();

        $this->get(route('scan.app'))->assertOk()->assertSee('Type pass code');
        $this->getJson(route('scan.bundle'))->assertOk();
    }

    // 6. lockout
    public function test_ten_wrong_codes_lock_the_ip_for_a_while_and_are_logged(): void
    {
        for ($i = 0; $i < ScannerController::MAX_WRONG_CODES; $i++) {
            $this->join('X', '000000')->assertSessionHasErrors('code');
        }
        $this->assertSame(ScannerController::MAX_WRONG_CODES, VolunteerJoin::where('result', 'wrong_code')->count());

        // Even the right code is refused now.
        $r = $this->join('X');
        $r->assertSessionHasErrors('code');
        $this->assertStringContainsString('Too many wrong codes', session('errors')->first('code'));
        $this->assertSame('locked_out', VolunteerJoin::latest('id')->value('result'));

        // Organizer sees the count.
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);
        $this->get(Volunteers::getUrl(tenant: $this->event))->assertOk()->assertSee('failed join attempts');
    }

    // 8. join log
    public function test_successful_joins_are_logged_with_device_and_ip(): void
    {
        $this->join();
        $j = VolunteerJoin::latest('id')->first();
        $this->assertSame('ok', $j->result);
        $this->assertSame($this->event->id, $j->event_id);
        $this->assertNotNull($j->user_id);
        $this->assertSame(12, strlen($j->device));
        $this->assertNotNull($j->ip);
    }
}
