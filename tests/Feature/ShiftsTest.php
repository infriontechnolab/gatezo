<?php

namespace Tests\Feature;

use App\Filament\Resources\Shifts\Pages\CreateShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Filament\Widgets\OnDutyBoard;
use App\Models\Event;
use App\Models\Shift;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftsTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    private $main;

    private $side;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Roster Fest']);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->main = $this->event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);
        $this->side = $this->event->gates()->create(['name' => 'Side Gate', 'code' => 'G2']);
    }

    private function join(string $name): void
    {
        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => $name]);
    }

    public function test_shift_planned_by_name_links_when_that_volunteer_joins_and_scanner_shows_it(): void
    {
        $shift = $this->event->shifts()->create([
            'volunteer_name' => 'Ravi Patel', 'gate_id' => $this->main->id,
            'starts_at' => now()->subMinutes(10), 'ends_at' => now()->addHours(2), 'label' => 'Registration desk',
        ]);
        $this->assertNull($shift->volunteer_id);

        $this->join('  ravi   patel '); // spacing/case ignored

        $shift->refresh();
        $this->assertNotNull($shift->volunteer_id);
        $this->assertSame('ravi   patel', trim(User::find($shift->volunteer_id)->name));

        $this->get(route('scan.app'))->assertOk()
            ->assertSee('Your shift:')->assertSee('Main Gate')->assertSee('Registration desk')
            ->assertSee('gate_id\\u0022:'.$this->main->id, false); // @js() output: preselect config for the scanner
    }

    public function test_unrelated_name_does_not_link_and_scanner_has_no_banner(): void
    {
        $this->event->shifts()->create(['volunteer_name' => 'Ravi', 'gate_id' => $this->main->id, 'starts_at' => now(), 'ends_at' => now()->addHour()]);
        $this->join('Priya');

        $this->assertNull($this->event->shifts()->first()->volunteer_id);
        $this->get(route('scan.app'))->assertOk()->assertDontSee('Your shift:');
    }

    public function test_status_transitions(): void
    {
        $mk = fn (array $a) => $this->event->shifts()->create(['volunteer_name' => 'Ravi', 'gate_id' => $this->main->id] + $a);

        $this->assertSame('upcoming', $mk(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)])->status());
        $this->assertSame('unlinked', $mk(['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()])->status());
        $this->assertSame('late', $mk(['starts_at' => now()->subMinutes(30), 'ends_at' => now()->addHour()])->status());

        // Ravi joins: shifts link, but he hasn't scanned a sign yet.
        $this->join('Ravi');
        $live = $this->event->shifts()->forName('Ravi')->where('starts_at', '<', now())->where('ends_at', '>', now())->orderBy('starts_at')->get();
        $this->assertSame(['late', 'starting'], $live->map->status()->all()); // 30 min ago → late; 5 min ago → within grace

        // Scans the Side Gate sign → elsewhere; then Main Gate → on duty.
        $this->postJson(route('scan.duty'), ['gate_id' => $this->side->id, 'status' => 'on'])->assertOk();
        $this->assertSame('elsewhere', $live->first()->fresh()->status());
        $this->postJson(route('scan.duty'), ['gate_id' => $this->main->id, 'status' => 'on'])->assertOk();
        $this->assertSame('on_duty', $live->first()->fresh()->status());

        // A finished shift he was on during → done; one he never attended → missed.
        $ravi = User::find($live->first()->fresh()->volunteer_id);
        $done = $mk(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $done->update(['volunteer_id' => $ravi->id]);
        $this->event->dutyLogs()->create(['volunteer_id' => $ravi->id, 'gate_id' => $this->main->id, 'status' => 'on', 'at' => now()->subHours(2)]);
        $this->assertSame('done', $done->fresh()->status());

        $missed = $mk(['starts_at' => now()->subHours(8), 'ends_at' => now()->subHours(6)]);
        $missed->update(['volunteer_id' => $ravi->id]);
        $this->assertSame('missed', $missed->fresh()->status());
    }

    public function test_resource_create_links_an_already_joined_volunteer_and_list_renders_status(): void
    {
        $this->join('Meera');
        $this->flushSession();
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(CreateShift::class)
            ->fillForm(['volunteer_name' => 'meera', 'gate_id' => $this->side->id, 'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHour()])
            ->call('create')
            ->assertHasNoFormErrors();

        $shift = $this->event->shifts()->first();
        $this->assertNotNull($shift->volunteer_id, 'linked to the volunteer who already joined');

        Livewire::test(ListShifts::class)->assertSee('meera')->assertSee('Side Gate')->assertSee('Starting'); // started 5 min ago, within grace, not scanned in
    }

    public function test_board_shows_rostered_post_and_not_arrived_gaps(): void
    {
        $this->event->shifts()->create(['volunteer_name' => 'Absent Amit', 'gate_id' => $this->side->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->event->shifts()->create(['volunteer_name' => 'Ravi', 'gate_id' => $this->main->id, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $this->join('Ravi');
        $this->postJson(route('scan.duty'), ['gate_id' => $this->side->id, 'status' => 'on']);

        $this->flushSession();
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(OnDutyBoard::class)
            ->assertSee('Ravi')->assertSee('Side Gate')   // actual
            ->assertSee('Main Gate')                       // rostered
            ->assertSee('Not arrived: Absent Amit (Side Gate)');
    }

    public function test_report_lists_the_roster(): void
    {
        $this->event->shifts()->create(['volunteer_name' => 'Ravi', 'gate_id' => $this->main->id, 'starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);

        $this->actingAs($this->organizer)->get(route('print.report', $this->event))
            ->assertOk()->assertSee('Volunteer roster')->assertSee('Ravi')->assertSee('Missed');
    }
}
