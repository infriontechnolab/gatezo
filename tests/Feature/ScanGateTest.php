<?php

use App\Enums\CheckinDirection;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Gate;
use App\Models\Pass;
use App\Services\PassToken;

it('records a scan without the gate when the gate is not this event\'s', function () {
    $event = Event::factory()->create();
    $ours = Gate::factory()->for($event)->create();
    $theirs = Gate::factory()->create();
    $first = Pass::factory()->for(Attendee::factory()->for($event))->create();
    $second = Pass::factory()->for(Attendee::factory()->for($event))->create();
    $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);
    $scan = fn (Pass $pass, int $gateId) => [
        'client_id' => (string) str()->uuid(), 'token' => PassToken::current($pass, $event), 'gate_id' => $gateId,
        'direction' => CheckinDirection::In->value, 'scanned_at' => now()->toIso8601String(),
    ];

    $this->postJson(route('scan.sync'), ['scans' => [$scan($first, $theirs->id), $scan($second, $ours->id)]])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'ok')
        ->assertJsonPath('results.1.status', 'ok');

    expect($first->checkins()->sole()->gate_id)->toBeNull()
        ->and($second->checkins()->sole()->gate_id)->toBe($ours->id);
});
