<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Team;
use App\Filament\Pages\Tenancy\RegisterEvent;
use App\Filament\Resources\Attendees\Pages\CreateAttendee;
use App\Models\Event;
use App\Models\User;
use App\Notifications\NewSignup;
use App\Services\AttendeeImporter;
use App\Support\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Self-serve sign-up lands on the free plan; the plan's caps bite in every place attendees or events get created. */
class SignupPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gatezo.plans.free.attendees' => 2]); // small cap so the tests stay quick
    }

    private function freeOrganizerWithEvent(): array
    {
        $user = User::factory()->create(['plan' => 'free']);
        $event = Event::create(['name' => 'Society Garba']);
        $event->forceFill(['created_by' => $user->id])->save();
        $event->members()->attach($user->id, ['role' => 'organizer']);

        return [$user, $event];
    }

    // ---- Sign-up ----------------------------------------------------------------

    public function test_register_page_is_public_and_login_links_to_it(): void
    {
        $this->get('/admin/register')->assertOk()->assertSee('Start your event')->assertSee('WhatsApp number');
        $this->get('/admin/login')->assertOk()->assertSee('/admin/register');
    }

    public function test_signup_creates_free_user_with_normalised_phone_and_notifies_us(): void
    {
        Notification::fake();
        config(['gatezo.signup_notify' => 'hello@gatezo.example']);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Bhavesh Patel',
                'email' => 'bhavesh@example.com',
                'phone' => '+91 98765 43210',
                'password' => 'correct-horse-battery',
                'passwordConfirmation' => 'correct-horse-battery',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'bhavesh@example.com')->firstOrFail();
        $this->assertSame('free', $user->plan);
        $this->assertSame('9876543210', $user->phone);
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo(new AnonymousNotifiable, NewSignup::class, function (NewSignup $n, array $channels, AnonymousNotifiable $to) use ($user) {
            return $to->routes['mail'] === 'hello@gatezo.example' && $n->user->is($user);
        });
    }

    public function test_signup_requires_a_valid_phone(): void
    {
        Livewire::test(Register::class)
            ->fillForm(['name' => 'X', 'email' => 'x@example.com', 'phone' => 'call me', 'password' => 'correct-horse-battery', 'passwordConfirmation' => 'correct-horse-battery'])
            ->call('register')
            ->assertHasFormErrors(['phone']);

        $this->assertNull(User::where('email', 'x@example.com')->first());
    }

    // ---- Events per organizer ---------------------------------------------------

    public function test_free_user_creates_one_event_then_the_page_and_duplicate_go_away(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $this->actingAs($user);

        $this->assertTrue(RegisterEvent::canView());
        Livewire::test(RegisterEvent::class)->fillForm(['name' => 'First Fest', 'type' => 'festival'])->call('register')->assertHasNoFormErrors();

        $event = Event::where('name', 'First Fest')->firstOrFail();
        $this->assertSame($user->id, $event->created_by);
        $this->assertFalse(RegisterEvent::canView());
        $this->get('/admin/new')->assertNotFound();

        Filament::setTenant($event, isQuiet: true);
        Livewire::test(Dashboard::class)->assertActionHidden('duplicate');
    }

    public function test_pro_user_is_uncapped(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        $this->actingAs($user);

        foreach (['One', 'Two', 'Three'] as $name) {
            Livewire::test(RegisterEvent::class)->fillForm(['name' => $name, 'type' => 'other'])->call('register')->assertHasNoFormErrors();
        }
        $this->assertSame(3, Plan::eventsUsed($user));
        $this->assertTrue(RegisterEvent::canView());
    }

    public function test_being_invited_to_someone_elses_event_does_not_use_your_allowance(): void
    {
        [$owner, $event] = $this->freeOrganizerWithEvent();
        $guest = User::factory()->create(['plan' => 'free']);
        $event->members()->attach($guest->id, ['role' => 'organizer']);

        $this->actingAs($guest);
        $this->assertTrue(Plan::canCreateEvent($guest));
        $this->assertFalse(Plan::canCreateEvent($owner));
    }

    // ---- Registrations per event ------------------------------------------------

    public function test_public_registration_stops_at_the_cap_but_returning_attendees_still_find_their_pass(): void
    {
        [, $event] = $this->freeOrganizerWithEvent();

        $this->post("/e/{$event->slug}/register", ['name' => 'Aarti', 'phone' => '9800000001'])->assertRedirect();
        $this->post("/e/{$event->slug}/register", ['name' => 'Bina', 'phone' => '9800000002'])->assertRedirect();
        $this->assertTrue(Plan::isFull($event));

        $this->post("/e/{$event->slug}/register", ['name' => 'Chirag', 'phone' => '9800000003'])
            ->assertSessionHasErrors('name');
        $this->assertSame(2, $event->attendees()->count());

        // Aarti lost her pass: same name + phone still hands it back.
        $this->post("/e/{$event->slug}/register", ['name' => 'Aarti', 'phone' => '9800000001'])
            ->assertRedirect()->assertSessionHas('existing_pass', true);

        $this->get("/e/{$event->slug}")->assertOk()->assertSee('Registration is full');
    }

    public function test_events_without_an_owner_are_uncapped(): void
    {
        $event = Event::create(['name' => 'Seeded']);
        $this->assertNull(Plan::attendeeLimit($event));
        $this->assertFalse(Plan::isFull($event));
    }

    public function test_csv_import_stops_at_the_cap_and_says_so(): void
    {
        [, $event] = $this->freeOrganizerWithEvent();
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "name,phone\nA,9800000001\nB,9800000002\nC,9800000003\nD,9800000004\n");

        $stats = AttendeeImporter::import($event, $path);
        unlink($path);

        $this->assertSame(2, $stats['created']);
        $this->assertSame(2, $stats['skipped']);
        $this->assertStringContainsString('Upgrade to Pro', implode(' ', $stats['errors']));
        $this->assertSame(2, $event->passes()->count());
    }

    public function test_manual_add_in_the_panel_is_blocked_when_full(): void
    {
        [$user, $event] = $this->freeOrganizerWithEvent();
        $event->attendees()->createMany([['name' => 'A'], ['name' => 'B']]);
        $this->actingAs($user);
        Filament::setTenant($event, isQuiet: true);

        Livewire::test(CreateAttendee::class)->fillForm(['name' => 'C'])->call('create')->assertNotified('Registration is full');
        $this->assertSame(2, $event->attendees()->count());
    }

    // ---- Team -----------------------------------------------------------------

    public function test_free_plan_cannot_invite_organizers(): void
    {
        [$user, $event] = $this->freeOrganizerWithEvent();
        $this->actingAs($user);
        Filament::setTenant($event, isQuiet: true);

        Livewire::test(Team::class)->assertActionHidden('invite')->assertActionVisible('upgrade');

        $user->update(['plan' => 'pro']);
        Filament::setTenant($event->fresh(), isQuiet: true); // a real request reloads the tenant
        Livewire::test(Team::class)->assertActionVisible('invite')->assertActionHidden('upgrade');
    }

    // ---- Banner + commands ----------------------------------------------------

    public function test_free_plan_banner_appears_only_near_the_cap_and_only_for_the_owner(): void
    {
        config(['gatezo.plans.free.attendees' => 10]);
        [$user, $event] = $this->freeOrganizerWithEvent();
        $url = "/admin/{$event->slug}";

        // Quiet while there is room.
        $event->attendees()->createMany(array_map(fn ($i) => ['name' => "A{$i}"], range(1, 8)));
        $this->actingAs($user)->get($url)->assertOk()->assertDontSee('Free plan');

        // Last 10%: heads-up.
        $event->attendees()->create(['name' => 'A9']);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('1 of 10 registrations left')->assertSee('Upgrade to Pro');

        // Full: amber, and says sign-ups are refused.
        $event->attendees()->create(['name' => 'A10']);
        $this->actingAs($user)->get($url)->assertOk()->assertSee('registration is full')->assertSee('plan-banner-full');

        // An invited organizer is on someone else's plan: never nagged.
        $guest = User::factory()->create(['plan' => 'free']);
        $event->members()->attach($guest->id, ['role' => 'organizer']);
        $this->actingAs($guest)->get($url)->assertOk()->assertDontSee('Free plan');
    }

    public function test_plan_command_shows_and_changes_plan(): void
    {
        [$user] = $this->freeOrganizerWithEvent();

        $this->artisan('gatezo:plan', ['email' => $user->email])->expectsOutputToContain('Plan: free · events created: 1 of 1')->assertSuccessful();
        $this->artisan('gatezo:plan', ['email' => $user->email, 'plan' => 'pro'])->expectsOutputToContain('is now on pro')->assertSuccessful();
        $this->assertSame('pro', $user->fresh()->plan);
        $this->artisan('gatezo:plan', ['email' => $user->email, 'plan' => 'gold'])->assertExitCode(2);
        $this->artisan('gatezo:plan', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_hand_onboarded_organizers_are_pro_by_default(): void
    {
        Notification::fake();
        $this->artisan('gatezo:organizer', ['name' => 'Hetal', 'email' => 'hetal@example.com', '--no-mail' => true])->assertSuccessful();
        $this->assertSame('pro', User::where('email', 'hetal@example.com')->firstOrFail()->plan);

        $this->artisan('gatezo:organizer', ['name' => 'Jeet', 'email' => 'jeet@example.com', '--plan' => 'free', '--no-mail' => true])->assertSuccessful();
        $this->assertSame('free', User::where('email', 'jeet@example.com')->firstOrFail()->plan);
    }
}
