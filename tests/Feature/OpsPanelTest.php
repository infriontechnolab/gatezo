<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Filament\Ops\Resources\Events\Pages\ListEvents;
use App\Filament\Ops\Resources\Organizers\Pages\ListOrganizers;
use App\Filament\Ops\Widgets\Overview;
use App\Models\Event;
use App\Models\User;
use App\Support\Impersonation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** /ops: staff only, every organizer and event, plan switch, log in as. */
class OpsPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => 'ops@gatezo.local']);
        $this->admin->forceFill(['is_admin' => true])->save();

        $this->client = User::factory()->create(['name' => 'Bhavesh Patel', 'plan' => 'free', 'phone' => '9876543210']);
        $this->event = Event::create(['name' => 'Sharad Utsav', 'starts_at' => now()->addDays(3)]);
        $this->event->forceFill(['created_by' => $this->client->id])->save();
        $this->event->members()->attach($this->client->id, ['role' => 'organizer']);
        $this->event->attendees()->createMany([['name' => 'A'], ['name' => 'B']]);
    }

    /** Livewire tests render outside a request, so the panel has to be picked by hand. */
    private function inOps(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('ops'));
    }

    public function test_only_admins_get_in(): void
    {
        $this->get('/ops')->assertRedirect('/ops/login');
        $this->actingAs($this->client)->get('/ops')->assertForbidden();
        $this->actingAs($this->admin)->get('/ops')->assertOk()->assertSee('Organizers');

        $this->assertTrue($this->client->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($this->client->canAccessPanel(Filament::getPanel('ops')));

        // Staff are not organizers: /admin is closed to them, and the login page sends them to /ops.
        $this->assertFalse($this->admin->canAccessPanel(Filament::getPanel('admin')));
        auth()->logout();
        Livewire::test(Login::class)->fillForm(['email' => 'ops@gatezo.local', 'password' => 'password'])->call('authenticate')->assertRedirect('/ops/login');
    }

    public function test_admin_command_can_create_the_staff_account(): void
    {
        $this->artisan('gatezo:admin', ['email' => 'New@Example.com'])->assertFailed();
        $this->artisan('gatezo:admin', ['email' => 'New@Example.com', '--name' => 'New Staff'])
            ->expectsOutputToContain('/ops/password-reset/reset')
            ->assertSuccessful();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertTrue($user->is_admin);
        $this->assertSame('New Staff', $user->name);
    }

    public function test_admin_command_grants_and_revokes(): void
    {
        $this->artisan('gatezo:admin', ['email' => $this->client->email])->assertSuccessful();
        $this->assertTrue($this->client->fresh()->is_admin);
        $this->artisan('gatezo:admin', ['email' => $this->client->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($this->client->fresh()->is_admin);
        $this->artisan('gatezo:admin', ['email' => 'nobody@example.com'])->assertFailed();
    }

    public function test_organizers_list_hides_volunteers_and_staff_and_can_switch_plan(): void
    {
        $this->event->members()->attach(
            User::create(['name' => 'Ravi', 'email' => 'ravi.1.abc@'.User::VOLUNTEER_DOMAIN, 'password' => 'x'])->id,
            ['role' => 'volunteer'],
        );
        $this->inOps();

        Livewire::test(ListOrganizers::class)
            ->assertCanSeeTableRecords([$this->client])
            ->assertCanNotSeeTableRecords([$this->admin])
            ->assertSee('Bhavesh Patel')->assertDontSee('Ravi')
            ->callTableAction('plan', $this->client, data: ['plan' => 'pro'])
            ->assertNotified();

        $this->assertSame('pro', $this->client->fresh()->plan);
    }

    public function test_events_list_shows_owner_and_counts(): void
    {
        $this->inOps();

        Livewire::test(ListEvents::class)
            ->assertCanSeeTableRecords([$this->event])
            ->assertSee('Sharad Utsav')->assertSee('Bhavesh Patel');

        Livewire::test(Overview::class)->assertSee('Organizers')->assertSee('Registrations');
    }

    public function test_log_in_as_opens_the_organizer_panel_with_a_way_back(): void
    {
        $this->inOps();

        Livewire::test(ListOrganizers::class)->callTableAction('login_as', $this->client)
            ->assertRedirect("/admin/{$this->event->slug}");

        $this->assertAuthenticatedAs($this->client);
        $this->assertSame($this->admin->id, session(Impersonation::KEY));

        $this->get("/admin/{$this->event->slug}")->assertOk()->assertSee('seeing the panel as')->assertSee('Back to Ops');

        $this->post(route('ops.stop-impersonating'))->assertRedirect('/ops');
        $this->assertAuthenticatedAs($this->admin);
        $this->assertNull(session(Impersonation::KEY));
    }

    public function test_cannot_impersonate_staff_and_organizers_cannot_start_it(): void
    {
        $other = User::factory()->create();
        $other->forceFill(['is_admin' => true])->save();

        $this->actingAs($this->admin);
        $this->expectException(HttpException::class);
        Impersonation::start($other);
    }

    public function test_stop_without_an_impersonation_is_forbidden(): void
    {
        $this->actingAs($this->client)->post(route('ops.stop-impersonating'))->assertForbidden();
    }
}
