<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Pass;

it('gives volunteers a detail to confirm, never the full phone or email', function () {
    $event = Event::factory()->create();
    $byPhone = Pass::factory()->for(Attendee::factory()->for($event)->create(['name' => 'Aarti Shah', 'phone' => '9824017351', 'email' => 'aarti@example.com']))->create();
    $byEmail = Pass::factory()->for(Attendee::factory()->for($event)->create(['name' => 'Bina Rao', 'phone' => null, 'email' => 'bina@example.com']))->create();
    $neither = Pass::factory()->for(Attendee::factory()->for($event)->create(['name' => 'Chetan Mehta', 'phone' => null, 'email' => null]))->create();
    $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

    $passes = collect($this->getJson(route('scan.bundle'))->assertOk()->json('passes'))->keyBy('code');

    expect($passes[$byPhone->code]['hint'])->toBe('phone ends 7351')
        ->and($passes[$byEmail->code]['hint'])->toBe('email b…@example.com')
        ->and($passes[$neither->code]['hint'])->toBeNull()
        ->and(json_encode($passes))->not->toContain('9824017351')->not->toContain('aarti@example.com');
});
