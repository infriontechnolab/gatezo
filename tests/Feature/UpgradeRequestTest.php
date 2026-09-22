<?php

namespace Tests\Feature;

use App\Filament\Ops\Resources\UpgradeRequests\Pages\ListUpgradeRequests;
use App\Filament\Ops\Resources\UpgradeRequests\UpgradeRequestResource;
use App\Filament\Pages\Upgrade;
use App\Models\Event;
use App\Models\UpgradeRequest;
use App\Models\User;
use App\Notifications\UpgradeRequested;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Upgrade page in the organizer panel → request recorded → Ops flips the plan. */
class UpgradeRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create(['name' => 'Bhavesh Patel', 'plan' => 'free', 'phone' => '9876543210']);
        $this->event = Event::create(['name' => 'Sharad Utsav']);
        $this->event->forceFill(['created_by' => $this->organizer->id])->save();
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
    }

    public function test_upgrade_page_compares_plans_and_is_in_the_sidebar_for_free_users_only(): void
    {
        config(['gatezo.pro_price' => '₹2,999 per event']);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()
            ->assertSee('Unlimited')->assertSee('₹2,999 per event')->assertSee('Request Pro');
        $this->assertTrue(Upgrade::shouldRegisterNavigation());

        $this->organizer->update(['plan' => 'pro']);
        $this->assertFalse(Upgrade::shouldRegisterNavigation());
        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()->assertSee("You're on Pro")->assertDontSee('Request Pro');
    }

    public function test_every_upgrade_link_in_the_panel_goes_to_the_page_not_straight_to_whatsapp(): void
    {
        config(['gatezo.plans.free.attendees' => 1]);
        $this->event->attendees()->create(['name' => 'A']);
        $this->actingAs($this->organizer);

        $page = Upgrade::getUrl(tenant: $this->event);
        $this->get("/admin/{$this->event->slug}")->assertOk()->assertSee('href="'.$page.'"', false);
        $this->get("/admin/{$this->event->slug}/team")->assertOk()->assertSee($page);
    }

    public function test_requesting_pro_records_it_mails_us_and_hands_off_to_whatsapp(): void
    {
        Notification::fake();
        config(['gatezo.signup_notify' => 'hello@gatezo.example']);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(Upgrade::class)
            ->callAction('request', data: ['note' => '1,500 people, two gates'])
            ->assertHasNoActionErrors()
            ->assertNotified('Request sent')
            ->assertRedirect();

        $request = UpgradeRequest::firstOrFail();
        $this->assertSame($this->organizer->id, $request->user_id);
        $this->assertSame($this->event->id, $request->event_id);
        $this->assertSame('pending', $request->status);
        $this->assertSame('1,500 people, two gates', $request->note);
        $this->assertSame('free', $this->organizer->fresh()->plan); // nothing changes until Ops says so

        Notification::assertSentTo(new AnonymousNotifiable, UpgradeRequested::class, fn (UpgradeRequested $n) => $n->request->is($request));

        // Second click while one is pending: button gone, page says so.
        Livewire::test(Upgrade::class)->assertActionHidden('request');
        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()->assertSee('Request received');
    }

    public function test_ops_marks_pro_which_upgrades_the_account_or_dismisses(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_admin' => true])->save();
        $request = UpgradeRequest::create(['user_id' => $this->organizer->id, 'event_id' => $this->event->id]);
        $other = UpgradeRequest::create(['user_id' => User::factory()->create(['plan' => 'free'])->id]);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));

        Livewire::test(ListUpgradeRequests::class)
            ->assertCanSeeTableRecords([$request, $other])
            ->assertSee('Bhavesh Patel')->assertSee('Sharad Utsav')
            ->callTableAction('done', $request)->assertNotified()
            ->callTableAction('dismiss', $other)->assertNotified();

        $this->assertSame('pro', $this->organizer->fresh()->plan);
        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame($staff->id, $request->fresh()->handled_by);
        $this->assertSame('dismissed', $other->fresh()->status);
        $this->assertSame('free', $other->user->fresh()->plan);

        $this->assertNull(UpgradeRequestResource::getNavigationBadge());
    }
}
