<?php

namespace Tests\Feature;

use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Event;
use App\Models\Shift;
use App\Models\User;
use App\Models\VolunteerJoin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** Personal scanner links: no code to leak, bound to one phone, organizer can reissue. */
class VolunteerInviteTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = Event::create(['name' => 'Link Fest', 'ends_at' => now()->addDay()]);
        $gate = $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);
        $this->shift = $this->event->shifts()->create(['volunteer_name' => 'Ravi Patel', 'gate_id' => $gate->id, 'starts_at' => now()]);
    }

    public function test_every_shift_gets_a_token_and_the_link_opens_the_scanner_approved_with_the_shift_linked(): void
    {
        $this->event->update(['require_volunteer_approval' => true]);
        $this->assertNotNull($this->shift->invite_token);
        $this->assertArrayNotHasKey('invite_token', $this->shift->toArray()); // never leaks into JSON/bundles

        $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));
        $this->get(route('scan.app'))->assertOk()->assertSee('Ravi Patel')->assertDontSee('waiting for');

        $shift = $this->shift->fresh();
        $this->assertNotNull($shift->invite_used_at);
        $this->assertNotNull($shift->volunteer_id);
        $this->assertSame('Ravi Patel', $shift->volunteer->name);
        $this->assertNotNull($shift->volunteer->volunteerPivot($this->event)->approved_at); // organizer sent it: approved
        $this->assertSame('invite', VolunteerJoin::latest('id')->value('result'));
    }

    /** The test client drops response cookies; a real phone keeps eq_device for a year. */
    private function samePhone(TestResponse $response): static
    {
        return $this->withCookie('eq_device', $response->getCookie('eq_device')->getValue());
    }

    public function test_link_is_bound_to_the_first_phone(): void
    {
        $first = $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));
        $device = $this->shift->fresh()->invite_device;

        // Same phone, new session (e.g. session expired): fine.
        $this->flushSession();
        $this->samePhone($first)->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));

        // Forwarded to another phone: dead.
        $this->flushSession();
        $this->withCookie('eq_device', 'someone-elses-phone')->get($this->shift->inviteUrl())->assertOk()->assertSee('already opened on another phone');
        $this->assertSame($device, $this->shift->fresh()->invite_device);
        $this->assertSame('invite_used', VolunteerJoin::latest('id')->value('result'));
        $this->get(route('scan.app'))->assertRedirect(); // no session for the second phone
    }

    public function test_reset_issues_a_new_token_and_kills_the_old_link(): void
    {
        $old = $this->shift->invite_token;
        $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));

        $this->shift->resetInvite();
        $this->assertNotSame($old, $this->shift->invite_token);
        $this->assertNull($this->shift->invite_used_at);

        $this->flushSession();
        $this->get(route('scan.invite', $old))->assertNotFound();
        $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));
    }

    public function test_link_expires_a_day_after_the_event_and_bad_tokens_404(): void
    {
        $this->event->update(['ends_at' => now()->subDays(2)]);
        $this->get($this->shift->inviteUrl())->assertOk()->assertSee('expired');
        $this->get(route('scan.invite', 'nope'))->assertNotFound();
    }

    public function test_kicked_volunteer_cannot_come_back_through_the_link(): void
    {
        $first = $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));
        $volunteer = $this->shift->fresh()->volunteer;
        $this->event->members()->updateExistingPivot($volunteer->id, ['kicked_at' => now()]);

        $this->flushSession();
        $this->samePhone($first)->get($this->shift->inviteUrl())->assertOk()->assertSee('removed from this event');
    }

    public function test_code_join_can_be_switched_off_so_only_links_work(): void
    {
        $this->event->update(['join_by_code' => false]);

        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => 'Ravi Patel'])
            ->assertSessionHasErrors('code');
        $this->assertSame('code_off', VolunteerJoin::latest('id')->value('result'));

        $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));
        $this->get(route('scan.app'))->assertOk();
    }

    public function test_copying_a_shift_gives_the_copy_its_own_link(): void
    {
        $this->get($this->shift->inviteUrl())->assertRedirect(route('scan.app'));

        $copy = $this->shift->fresh()->replicate();
        $copy->volunteer_id = null;
        $copy->save();

        $this->assertNotSame($this->shift->invite_token, $copy->invite_token);
        $this->assertNull($copy->invite_used_at);
        $this->assertNull($copy->invite_device);
    }

    public function test_organizer_gets_the_link_from_the_shifts_table(): void
    {
        $organizer = User::factory()->create();
        $this->event->members()->attach($organizer->id, ['role' => 'organizer']);
        $this->actingAs($organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(ListShifts::class)
            ->assertTableActionHidden('reset_invite', $this->shift)
            ->callTableAction('invite', $this->shift)
            ->assertNotified();

        $this->shift->forceFill(['invite_used_at' => now(), 'invite_device' => 'abc'])->save();
        $old = $this->shift->invite_token;
        Livewire::test(ListShifts::class)
            ->assertTableActionVisible('reset_invite', $this->shift)
            ->callTableAction('reset_invite', $this->shift)
            ->assertNotified();
        $this->assertNotSame($old, $this->shift->fresh()->invite_token);
    }
}
