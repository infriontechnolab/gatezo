<?php

namespace Tests\Feature;

use App\Enums\BillingCycle;
use App\Enums\MemberRole;
use App\Filament\Ops\Resources\Plans\Pages\ManagePlans;
use App\Filament\Ops\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Ops\Resources\Subscriptions\SubscriptionResource;
use App\Models\Event;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Plan;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Paid plans as dated periods: on inside the dates, off outside them, managed from Ops. */
class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $staff;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create(['name' => 'Bhavesh Patel', 'plan' => 'free']);
        $this->event = Event::create(['name' => 'Property Expo']);
        $this->event->forceFill(['created_by' => $this->organizer->id])->save();
        $this->event->members()->attach($this->organizer->id, ['role' => MemberRole::Organizer]);
        $this->staff = User::factory()->create();
        $this->staff->forceFill(['is_admin' => true])->save();
    }

    private function period(string $from, string $until, string $plan = 'pro'): Subscription
    {
        return $this->organizer->subscriptions()->create(['plan' => $plan, 'starts_on' => $from, 'ends_on' => $until]);
    }

    public function test_pro_only_while_today_is_inside_a_period(): void
    {
        $this->assertSame(1, Plan::eventLimit($this->organizer));

        $this->period(today()->addDay()->toDateString(), today()->addMonth()->toDateString()); // booked, not started
        $this->assertTrue($this->organizer->fresh()->onFreePlan());

        $this->period(today()->subMonth()->toDateString(), today()->toDateString()); // last day is today: still Pro
        $this->assertSame('pro', $this->organizer->fresh()->currentPlan());
        $this->assertNull(Plan::eventLimit($this->organizer->fresh()));
        // Back-to-back renewal counts towards "until".
        $this->assertTrue($this->organizer->fresh()->paidUntil()->isSameDay(today()->addMonth()));

        $this->travel(2)->months();
        $this->assertTrue($this->organizer->fresh()->onFreePlan()); // lapsed on its own
        $this->assertSame(1, Plan::eventLimit($this->organizer->fresh()));
        $this->assertNull($this->organizer->fresh()->paidUntil());
    }

    public function test_base_plan_pro_has_no_end_date(): void
    {
        $this->organizer->update(['plan' => 'pro']);
        $this->assertSame('pro', $this->organizer->fresh()->currentPlan());
        $this->assertNull($this->organizer->fresh()->paidUntil());
        $this->assertSame(1, User::paying()->where('id', $this->organizer->id)->count());
    }

    public function test_each_plan_brings_its_own_caps_and_an_upgrade_mid_period_wins(): void
    {
        $this->period(today()->subDays(10)->toDateString(), today()->addDays(20)->toDateString(), 'starter');
        $this->assertSame('starter', $this->organizer->fresh()->currentPlan());
        $this->assertSame(3, Plan::eventLimit($this->organizer->fresh()));

        // Upgraded to Pro today while Starter still runs: Pro wins, Starter resumes if Pro ends first.
        $this->period(today()->toDateString(), today()->addDays(5)->toDateString(), 'pro');
        $this->assertSame('pro', $this->organizer->fresh()->currentPlan());
        $this->assertNull(Plan::eventLimit($this->organizer->fresh()));
        $this->travel(7)->days();
        $this->assertSame('starter', $this->organizer->fresh()->currentPlan());

        // Limits follow the catalogue: Ops edits Starter, the organizer feels it at once.
        $this->capPlan('starter', ['max_events' => 5]);
        $this->assertSame(5, Plan::eventLimit($this->organizer->fresh()));
    }

    public function test_paid_plans_count_events_per_month_from_the_period_start_even_when_billed_yearly(): void
    {
        $this->organizer->subscriptions()->create([
            'plan' => 'starter', 'billing' => BillingCycle::Yearly,
            'starts_on' => today()->subMonthNoOverflow()->subDays(5)->toDateString(), 'ends_on' => today()->addYear()->toDateString(),
        ]);
        $made = function (int $daysAgo): void {
            $event = Event::create(['name' => "Made {$daysAgo} days ago"]);
            $event->forceFill(['created_by' => $this->organizer->id, 'created_at' => now()->subDays($daysAgo)])->save();
        };
        $made(10); // last month of the period
        $made(2);  // this month, with the setUp event

        $this->assertSame(2, Plan::eventsUsed($this->organizer->fresh()));
        $made(1);
        $this->assertFalse(Plan::canCreateEvent($this->organizer->fresh())); // 3 of 3 this month

        $this->travel(1)->months();
        $this->assertSame(0, Plan::eventsUsed($this->organizer->fresh()));
        $this->assertTrue(Plan::canCreateEvent($this->organizer->fresh()));
    }

    public function test_a_plan_set_by_hand_counts_events_per_calendar_month(): void
    {
        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addDays(10));
        $this->organizer->update(['plan' => 'starter']);
        $old = Event::create(['name' => 'Last month']);
        $old->forceFill(['created_by' => $this->organizer->id, 'created_at' => now()->subMonth()])->save();

        $this->assertSame(0, Plan::eventsUsed($this->organizer->fresh())); // the setUp event and the other one are from earlier months
    }

    public function test_period_form_fills_end_date_and_amount_from_the_plan(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));

        Livewire::test(ManageSubscriptions::class)
            ->mountAction('create')
            ->setActionData(['user_id' => $this->organizer->id, 'starts_on' => '2026-01-31'])
            ->setActionData(['plan' => 'starter'])
            ->setActionData(['billing' => BillingCycle::Monthly->value])
            ->assertActionDataSet(['ends_on' => '2026-02-27', 'amount' => 199]) // no overflow into March
            ->setActionData(['billing' => BillingCycle::Yearly->value])
            ->assertActionDataSet(['ends_on' => '2027-01-30', 'amount' => 1499]);
    }

    public function test_ops_records_changes_and_ends_periods(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));

        Livewire::test(ManageSubscriptions::class)
            ->callAction('create', data: ['user_id' => $this->organizer->id, 'plan' => 'pro', 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDays(10)->toDateString(), 'amount' => 2999])
            ->assertHasNoActionErrors();
        $sub = Subscription::firstOrFail();
        $this->assertSame($this->staff->id, $sub->created_by);
        $this->assertSame('1', SubscriptionResource::getNavigationBadge()); // ends within 14 days

        Livewire::test(ManageSubscriptions::class)
            ->assertCanSeeTableRecords([$sub])
            ->callTableAction('edit', $sub, data: ['starts_on' => today()->toDateString(), 'ends_on' => today()->addYear()->toDateString()])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($sub->fresh()->ends_on->isSameDay(today()->addYear()));
        $this->assertNull(SubscriptionResource::getNavigationBadge());

        Livewire::test(ManageSubscriptions::class)->callTableAction('end', $sub)->assertNotified();
        $this->assertTrue($sub->fresh()->ends_on->isToday());
        $this->travel(1)->day();
        $this->assertTrue($this->organizer->fresh()->onFreePlan());
    }

    public function test_overlapping_periods_are_refused(): void
    {
        $this->period(today()->toDateString(), today()->addMonths(6)->toDateString());
        $this->actingAs($this->staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));

        Livewire::test(ManageSubscriptions::class)
            ->callAction('create', data: ['user_id' => $this->organizer->id, 'plan' => 'pro', 'starts_on' => today()->addMonth()->toDateString(), 'ends_on' => today()->addYear()->toDateString()])
            ->assertHasActionErrors(['ends_on']);
        $this->assertSame(1, Subscription::count());
    }

    public function test_renewal_defaults_to_the_day_after_current_pro(): void
    {
        $this->period(today()->subMonth()->toDateString(), today()->addDays(5)->toDateString());
        $this->assertTrue(SubscriptionResource::nextStart($this->organizer)->isSameDay(today()->addDays(6)));
        $this->assertTrue(SubscriptionResource::nextStart(User::factory()->create())->isToday());
    }

    public function test_owner_is_warned_a_week_before_pro_ends(): void
    {
        $this->period(today()->subMonth()->toDateString(), today()->addDays(3)->toDateString());
        $this->actingAs($this->organizer);

        $this->get("/admin/{$this->event->slug}")->assertOk()->assertSee('ends on '.today()->addDays(3)->format('j M'))->assertSee('Renew');
    }

    public function test_ops_edits_the_catalogue(): void
    {
        $this->actingAs($this->staff);
        Filament::setCurrentPanel(Filament::getPanel('ops'));
        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        Livewire::test(ManagePlans::class)
            ->assertCanSeeTableRecords(SubscriptionPlan::all())
            ->callTableAction('edit', $starter, data: ['price_monthly' => 1499, 'max_events' => 5])
            ->assertHasNoTableActionErrors()
            ->callAction('create', data: ['name' => 'Agency', 'slug' => 'agency', 'price_yearly' => 49999, 'sort' => 30])
            ->assertHasNoActionErrors()
            ->callAction('create', data: ['name' => 'Copy', 'slug' => 'starter'])
            ->assertHasActionErrors(['slug' => 'unique']);

        $this->assertSame(1499, SubscriptionPlan::bySlug('starter')->price_monthly);
        $this->assertSame(5, SubscriptionPlan::bySlug('starter')->max_events);
        $this->assertNull(SubscriptionPlan::bySlug('agency')->max_events); // empty = unlimited
        $this->assertSame(['starter', 'pro', 'agency'], SubscriptionPlan::forSale()->pluck('slug')->all());
    }
}
