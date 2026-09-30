<?php

use App\Enums\CheckinDirection;
use App\Models\Attendee;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Pass;

it('sends the scanner how many are inside when the event has a capacity', function () {
    $event = Event::factory()->create(['capacity' => 100]);
    $stayed = Pass::factory()->for(Attendee::factory()->for($event))->create();
    $left = Pass::factory()->for(Attendee::factory()->for($event))->create();
    Checkin::factory()->for($stayed)->create(['direction' => CheckinDirection::In]);
    Checkin::factory()->for($left)->create(['direction' => CheckinDirection::In, 'scanned_at' => now()->subHour()]);
    Checkin::factory()->for($left)->create(['direction' => CheckinDirection::Out]);
    $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

    $this->getJson(route('scan.bundle'))
        ->assertOk()
        ->assertJsonPath('inside', 1)
        ->assertJsonPath('event.capacity', 100);
});

it('leaves the inside count out without a capacity', function () {
    $event = Event::factory()->create(['capacity' => null]);
    $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

    $this->getJson(route('scan.bundle'))->assertOk()->assertJsonPath('inside', null);
});
