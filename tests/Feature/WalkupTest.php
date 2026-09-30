<?php

use App\Enums\AttendeeSource;
use App\Enums\CheckinDirection;
use App\Models\Attendee;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Gate;
use App\Models\Pass;

function joinAsVolunteer(Event $event): void
{
    test()->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);
}

it('registers a walk-up and checks them in at the volunteer\'s gate', function () {
    $event = Event::factory()->create();
    $gate = Gate::factory()->for($event)->create();
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Meera Joshi', 'phone' => '98240 17356', 'gate_id' => $gate->id])
        ->assertOk()
        ->assertJson(['status' => 'ok', 'existing' => false, 'name' => 'Meera Joshi'])
        ->assertJsonStructure(['code', 'qr', 'client_id']);

    $attendee = $event->attendees()->sole();
    expect($attendee->source)->toBe(AttendeeSource::Walkup)
        ->and($attendee->pass->checkins()->sole())
        ->gate_id->toBe($gate->id)
        ->direction->toBe(CheckinDirection::In);
});

it('checks in the existing pass when the phone already registered', function () {
    $event = Event::factory()->create();
    $attendee = Attendee::factory()->for($event)->create(['name' => 'Meera Joshi', 'phone' => '9824017356', 'source' => AttendeeSource::Online]);
    $pass = Pass::factory()->for($attendee)->create();
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Meera', 'phone' => '9824017356'])
        ->assertOk()
        ->assertJson(['status' => 'ok', 'existing' => true, 'code' => $pass->code]);

    expect($event->attendees()->count())->toBe(1)
        ->and($attendee->fresh()->source)->toBe(AttendeeSource::Online)
        ->and($pass->checkins()->count())->toBe(1);
});

it('flags a walk-up whose pass is already inside', function () {
    $event = Event::factory()->create();
    $attendee = Attendee::factory()->for($event)->create(['name' => 'Meera Joshi', 'phone' => '9824017356']);
    Checkin::factory()->for(Pass::factory()->for($attendee)->create())->create(['direction' => CheckinDirection::In]);
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Meera', 'phone' => '9824017356'])
        ->assertOk()
        ->assertJson(['status' => 'duplicate']);
});

it('refuses a phone registered under a different name', function () {
    $event = Event::factory()->create();
    Pass::factory()->for(Attendee::factory()->for($event)->create(['name' => 'Meera Joshi', 'phone' => '9824017356']))->create();
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Kunal Shah', 'phone' => '9824017356'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('refuses walk-ups when registration is closed', function () {
    $event = Event::factory()->create(['allow_self_register' => false]);
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Meera Joshi'])->assertForbidden();
    expect($event->attendees()->count())->toBe(0);
});

it('refuses a gate from another event', function () {
    $event = Event::factory()->create();
    $otherGate = Gate::factory()->create();
    joinAsVolunteer($event);

    $this->postJson(route('scan.walkup'), ['name' => 'Meera Joshi', 'gate_id' => $otherGate->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('gate_id');
});

it('needs a volunteer session', function () {
    $this->postJson(route('scan.walkup'), ['name' => 'Meera Joshi'])->assertUnauthorized();
});
