<?php

namespace Tests\Feature;

use App\Filament\Resources\Draws\Pages\StageDraw;
use App\Models\Draw;
use App\Models\DrawWinner;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;
use App\Services\DrawEngine;
use App\Services\PassToken;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DrawTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Event $event;

    private $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organizer = User::factory()->create();
        $this->event = Event::create(['name' => 'Draw Fest', 'allow_reentry' => true]);
        $this->event->members()->attach($this->organizer->id, ['role' => 'organizer']);
        $this->gate = $this->event->gates()->create(['name' => 'Main', 'code' => 'G1']);
    }

    /** name → [pass, inside?] */
    private function people(array $spec): array
    {
        $out = [];
        foreach ($spec as $name => $state) {
            $a = $this->event->attendees()->create(['name' => $name, 'phone' => '98'.str_pad((string) count($out), 8, '0', STR_PAD_LEFT)]);
            $p = $a->pass()->create(['event_id' => $this->event->id]);
            if (in_array($state, ['in', 'left'], true)) {
                $p->checkins()->create(['event_id' => $this->event->id, 'gate_id' => $this->gate->id, 'scanned_at' => now()->subMinutes(30), 'client_id' => (string) Str::uuid()]);
            }
            if ($state === 'left') {
                $p->checkins()->create(['event_id' => $this->event->id, 'gate_id' => $this->gate->id, 'direction' => 'out', 'scanned_at' => now()->subMinutes(5), 'client_id' => (string) Str::uuid()]);
            }
            $out[$name] = $p;
        }

        return $out;
    }

    private function draw(array $attrs = [], array $prizes = [['name' => 'Mixer', 'quantity' => 1]]): Draw
    {
        $d = $this->event->draws()->create(['name' => 'Test draw'] + $attrs);
        foreach ($prizes as $i => $p) {
            $d->prizes()->create($p + ['sort_order' => $i]);
        }

        return $d; // deliberately not fresh(): a just-created draw must be runnable
    }

    // ---- pool rules ------------------------------------------------------------

    public function test_pool_sources_and_filters(): void
    {
        $p = $this->people(['A' => 'in', 'B' => 'in', 'C' => 'left', 'D' => 'never']);
        $p['B']->update(['revoked' => true]);
        $p['A']->attendee->update(['ticket_type' => 'vip']);
        $this->event->feedback()->create(['attendee_id' => $p['C']->attendee_id, 'rating' => 5]);

        $names = fn (Draw $d) => DrawEngine::pool($d)->get()->map(fn (Pass $x) => $x->attendee->name)->sort()->values()->all();

        $this->assertSame(['A'], $names($this->draw(['pool_source' => 'inside_now'])));          // B revoked, C left, D never came
        $this->assertSame(['A', 'C'], $names($this->draw(['pool_source' => 'checked_in'])));
        $this->assertSame(['A', 'C', 'D'], $names($this->draw(['pool_source' => 'registered'])));
        $this->assertSame(['A'], $names($this->draw(['pool_source' => 'registered', 'filters' => ['ticket_types' => ['vip']]])));
        $this->assertSame(['C'], $names($this->draw(['pool_source' => 'registered', 'filters' => ['feedback_given' => true]])));
    }

    public function test_previous_winners_are_excluded_by_default(): void
    {
        $p = $this->people(['A' => 'in', 'B' => 'in']);
        $first = $this->draw();
        DrawEngine::run($first, $this->organizer);
        $w = DrawEngine::announceNext($first->fresh());
        DrawEngine::claim($w, $this->organizer);

        $second = $this->draw();
        $pool = DrawEngine::pool($second)->pluck('id')->all();
        $this->assertNotContains($w->pass_id, $pool);
        $this->assertCount(1, $pool);

        $third = $this->draw(['exclude_previous_winners' => false]);
        $this->assertCount(2, DrawEngine::pool($third)->get());
    }

    // ---- engine ----------------------------------------------------------------

    public function test_run_is_deterministic_from_seed_and_snapshot_and_commits_hash(): void
    {
        $this->people(array_fill_keys(['A', 'B', 'C', 'D', 'E', 'F'], 'in'));
        $d = $this->draw(['alternates_per_prize' => 1], [['name' => 'Voucher', 'quantity' => 2], ['name' => 'Scooter', 'quantity' => 1]]);

        $this->assertNull($d->pool_snapshot);
        $this->assertSame(hash('sha256', $d->getAttribute('seed')), $d->seed_hash, 'hash committed at create');

        DrawEngine::run($d, $this->organizer);
        $d->refresh();

        $this->assertSame('ready', $d->status);
        $this->assertCount(6, $d->pool_snapshot);
        // 3 slots × (1 winner + 1 backup) = 6 rows, one per person
        $this->assertSame(6, $d->winners()->count());
        $this->assertSame(6, $d->winners()->distinct('pass_id')->count('pass_id'));

        // Anyone can reproduce: order the snapshot by hmac(seed, code) and read off slots in prize order.
        $seed = $d->getAttribute('seed');
        $expected = collect($d->pool_snapshot)->sortBy(fn ($r) => hash_hmac('sha256', $r['code'], $seed))->pluck('code')->values();
        $actual = $d->winners()->with(['prize', 'pass'])->get()
            ->sortBy(fn ($w) => sprintf('%05d-%05d-%03d', $w->prize->sort_order, $w->slot, $w->rank))->map(fn ($w) => $w->pass->code)->values();
        $this->assertEquals($expected->all(), $actual->all());

        // Cannot run twice.
        $this->expectExceptionMessage('already been run');
        DrawEngine::run($d->fresh(), $this->organizer);
    }

    public function test_announce_claim_forfeit_and_backup_flow(): void
    {
        $this->people(array_fill_keys(['A', 'B', 'C', 'D'], 'in'));
        $d = $this->draw(['alternates_per_prize' => 1, 'claim_minutes' => 5], [['name' => 'Mixer', 'quantity' => 1], ['name' => 'TV', 'quantity' => 1]]);
        DrawEngine::run($d, $this->organizer);

        // Mixer winner on stage with a 5-minute deadline.
        $w1 = DrawEngine::announceNext($d->fresh());
        $this->assertSame(['Mixer', 1, 1, 'announced'], [$w1->prize->name, $w1->slot, $w1->rank, $w1->status]);
        $this->assertEqualsWithDelta(now()->addMinutes(5)->timestamp, $w1->claim_deadline->timestamp, 2);
        $this->assertNotNull($d->fresh()->current());

        // Can't announce another while one is on stage.
        try {
            DrawEngine::announceNext($d->fresh());
            $this->fail('should have refused');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        // Mixer winner doesn't show → forfeit → backup (rank 2) comes up for the same slot.
        DrawEngine::forfeit($w1);
        $w2 = DrawEngine::announceNext($d->fresh());
        $this->assertSame(['Mixer', 1, 2], [$w2->prize->name, $w2->slot, $w2->rank]);
        DrawEngine::claim($w2, $this->organizer);
        $this->assertSame('claimed', $w2->fresh()->status);

        // TV: winner claims; its unused backup row is dropped.
        $w3 = DrawEngine::announceNext($d->fresh());
        $this->assertSame('TV', $w3->prize->name);
        DrawEngine::claim($w3, $this->organizer);
        $this->assertSame(0, DrawWinner::where('draw_id', $d->id)->where('prize_id', $w3->prize_id)->where('status', 'pending')->count());

        // Nothing left → finished, seed revealed publicly.
        $this->assertNull(DrawEngine::announceNext($d->fresh()));
        $this->assertSame('finished', $d->fresh()->status);
        $this->assertSame($d->getAttribute('seed'), DrawEngine::publicState($d->fresh())['seed']);
    }

    // ---- panel + public surfaces --------------------------------------------------

    public function test_stage_page_actions_drive_the_draw(): void
    {
        $this->people(['A' => 'in', 'B' => 'in']);
        $d = $this->draw();
        $this->actingAs($this->organizer);
        Filament::setTenant($this->event, isQuiet: true);

        Livewire::test(StageDraw::class, ['record' => $d->id])
            ->assertSee('Seed committed')
            ->callAction('run')->assertNotified()
            ->callAction('announce')->assertNotified()
            ->assertSee('On stage: Mixer')
            ->callAction('claim')->assertNotified();

        $this->assertSame(1, $d->winners()->where('status', 'claimed')->count());
    }

    public function test_presenter_is_signed_and_results_are_public_with_proof(): void
    {
        $p = $this->people(['Aarti Shah' => 'in', 'Bhavesh Patel' => 'in']);
        $d = $this->draw(['alternates_per_prize' => 0]);

        $this->get(route('draw.stage', [$this->event, $d]))->assertForbidden();
        $this->get($d->presenterUrl())->assertOk()->assertSee('Waiting for the organizer');

        DrawEngine::run($d, $this->organizer);
        $w = DrawEngine::announceNext($d->fresh());
        $json = $this->getJson(URL::signedRoute('draw.stage.json', [$this->event, $d]))->assertOk()->json();
        $this->assertSame('announced', $json['current']['status']);
        $this->assertMatchesRegularExpression('/^\w+ [A-Z]\.$/', $json['current']['name'], 'short name on stage');
        $this->assertMatchesRegularExpression('/^98x+\d{4}$/', $json['current']['phone'], 'masked phone on stage');
        $this->assertNull($json['seed'], 'seed hidden until finished');

        DrawEngine::claim($w, $this->organizer);
        DrawEngine::announceNext($d->fresh());

        $this->get(route('draw.results', $this->event))->assertOk()
            ->assertSee('Mixer')->assertSee($d->seed_hash)->assertSee($d->getAttribute('seed'));
    }

    public function test_pass_page_shows_the_win_and_volunteer_can_claim_by_scanning(): void
    {
        $p = $this->people(['Dipti Joshi' => 'in']);
        $d = $this->draw(['alternates_per_prize' => 0]);
        DrawEngine::run($d, $this->organizer);
        $w = DrawEngine::announceNext($d->fresh());
        $pass = $w->pass;

        $this->get(route('pass.show', $pass))->assertOk()->assertSee('You have been drawn')->assertSee('Mixer');

        $this->post(route('scan.join.post'), ['code' => $this->event->volunteer_code, 'name' => 'Ravi']);
        $this->getJson(route('scan.bundle'))->assertOk()->assertJsonPath("winners.{$pass->code}.prize", 'Mixer');

        $this->postJson(route('scan.claim'), ['token' => PassToken::make($pass)])->assertOk()->assertJsonPath('prize', 'Mixer');
        $this->assertSame('claimed', $w->fresh()->status);
        $this->assertNotNull($w->fresh()->verified_by);
        $this->postJson(route('scan.claim'), ['token' => PassToken::make($pass)])->assertStatus(404); // no longer on stage

        $this->get(route('pass.show', $pass))->assertOk()->assertSee('You won');
    }
}
