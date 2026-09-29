<?php

namespace Tests\Feature;

use App\Filament\Ops\Resources\UpgradeRequests\Pages\ListUpgradeRequests;
use App\Filament\Ops\Resources\UpgradeRequests\UpgradeRequestResource;
use App\Filament\Pages\Upgrade;
use App\Models\Event;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\UpgradeRequest;
use App\Models\User;
use App\Notifications\UpgradeRequested;
use App\Support\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Plans page in the organizer panel → chosen plan recorded → we call → Ops confirms payment as a dated period. */
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

    public function test_plans_page_lists_what_is_on_sale(): void
    {
        $this->capPlan('starter', ['price_yearly' => null]);
        SubscriptionPlan::create(['slug' => 'agency', 'name' => 'Agency', 'price_yearly' => 99999, 'is_active' => false, 'sort' => 30]);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()
            ->assertSee('Starter')->assertSee('₹199 / month')->assertDontSee('₹1,499 / year') // yearly not sold
            ->assertSee('₹499 / month')->assertSee('₹3,999 / year')->assertSee('Unlimited')
            ->assertSee('Choose Starter')->assertSee('Choose Pro')
            ->assertDontSee('Agency'); // switched off
        $this->assertSame('Upgrade', Upgrade::getNavigationLabel());

        $this->organizer->update(['plan' => 'pro']); // comped, no end date
        $this->assertSame('Your plan', Upgrade::getNavigationLabel());
        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()->assertSee("You're on Pro")->assertSee('Your plan');
    }

    public function test_every_upgrade_link_in_the_panel_goes_to_the_page_not_straight_to_whatsapp(): void
    {
        $this->capPlan('free', ['max_attendees' => 1]);
        $this->event->attendees()->create(['name' => 'A']);
        $this->actingAs($this->organizer);

        $page = Upgrade::getUrl(tenant: $this->event);
        $this->get("/admin/{$this->event->slug}")->assertOk()->assertSee('href="'.$page.'"', false)
            ->assertDontSee('wa.me/'.config('gatezo.whatsapp'), false);
        $this->get("/admin/{$this->event->slug}/team")->assertOk()->assertSee($page);
    }

    public function test_choosing_a_plan_records_it_and_mails_us_for_a_call_back(): void
    {
        Notification::fake();
        config(['gatezo.signup_notify' => 'hello@gatezo.example']);
        $this->organizer->update(['phone' => null]);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(Upgrade::class)
            ->callAction('request', data: ['billing' => 'yearly', 'phone' => '+91 98765 43210', 'note' => 'Expo on 12 Oct, 3,000 people'], arguments: ['plan' => 'pro'])
            ->assertHasNoActionErrors()
            ->assertNotified('Request sent')
            ->assertNoRedirect(); // no hand-off to WhatsApp: we call them

        $request = UpgradeRequest::firstOrFail();
        $this->assertSame($this->organizer->id, $request->user_id);
        $this->assertSame($this->event->id, $request->event_id);
        $this->assertSame('pro', $request->plan);
        $this->assertSame('yearly', $request->billing);
        $this->assertSame('pending', $request->status);
        $this->assertSame('9876543210', $request->phone);
        $this->assertSame('Pro, yearly · ₹3,999 / year', $request->choiceLabel());
        $this->assertSame('9876543210', $this->organizer->fresh()->phone); // filled in, since they had none
        $this->assertTrue($this->organizer->fresh()->onFreePlan()); // nothing changes until Ops confirms payment

        Notification::assertSentTo(new AnonymousNotifiable, UpgradeRequested::class, fn (UpgradeRequested $n) => $n->request->is($request));

        // While one is pending: no choose buttons, the page says we'll call, and it can be cancelled.
        Livewire::test(Upgrade::class)->assertActionHidden('request');
        $this->get(Upgrade::getUrl(tenant: $this->event))->assertOk()->assertSee('Pro requested')->assertSee('9876543210');
        Livewire::test(Upgrade::class)->callAction('cancelRequest')->assertNotified('Request cancelled');
        $this->assertSame('dismissed', $request->fresh()->status);
    }

    public function test_plan_request_is_refused_for_a_billing_cycle_or_plan_not_on_sale(): void
    {
        $this->capPlan('starter', ['price_yearly' => null]);
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(Upgrade::class)
            ->callAction('request', data: ['billing' => 'yearly', 'phone' => '9876543210'], arguments: ['plan' => 'starter'])
            ->assertHasActionErrors(['billing']);
        Livewire::test(Upgrade::class)
            ->callAction('request', data: ['billing' => 'monthly', 'phone' => '123'], arguments: ['plan' => 'starter'])
            ->assertHasActionErrors(['phone']);
        $this->assertSame(0, UpgradeRequest::count());
    }

    public function test_ops_confirms_payment_for_the_chosen_plan_or_dismisses(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_admin' => true])->save();
        $request = UpgradeRequest::create(['user_id' => $this->organizer->id, 'event_id' => $this->event->id, 'plan' => 'starter', 'billing' => 'monthly']);
        $other = UpgradeRequest::create(['user_id' => User::factory()->create(['plan' => 'free'])->id, 'plan' => 'pro', 'billing' => 'yearly']);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));

        // The form starts from what they chose: Starter, a month from today, ₹199.
        Livewire::test(ListUpgradeRequests::class)
            ->assertCanSeeTableRecords([$request, $other])
            ->assertSee('Bhavesh Patel')->assertSee('Sharad Utsav')->assertSee('Starter')
            ->mountTableAction('done', $request)
            ->assertTableActionDataSet(['plan' => 'starter', 'billing' => 'monthly', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addMonthNoOverflow()->subDay()->toDateString(), 'amount' => 199])
            ->setTableActionData(['payment_ref' => 'UPI 4411'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()->assertNotified();
        Livewire::test(ListUpgradeRequests::class)->callTableAction('dismiss', $other)->assertNotified();

        $sub = Subscription::firstOrFail();
        $this->assertSame([$this->organizer->id, 'starter', 'monthly', 199, 'UPI 4411', $request->id, $staff->id],
            [$sub->user_id, $sub->plan, $sub->billing, $sub->amount, $sub->payment_ref, $sub->upgrade_request_id, $sub->created_by]);
        $this->assertSame('starter', $this->organizer->fresh()->currentPlan());
        $this->assertSame('free', $this->organizer->fresh()->plan); // base plan untouched: the dates decide
        $this->assertSame(1000, Plan::attendeeLimit($this->event->fresh()));

        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame('dismissed', $other->fresh()->status);
        $this->assertTrue($other->user->fresh()->onFreePlan());
        $this->assertNull(UpgradeRequestResource::getNavigationBadge());
    }
}
