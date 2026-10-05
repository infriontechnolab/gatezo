<?php

use App\Enums\CheckinDirection;
use App\Enums\EventType;
use App\Enums\MemberRole;
use App\Models\Attendee;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;

function attendedBy(Event $event, string $phone): Attendee
{
    $attendee = Attendee::factory()->for($event)->create(['phone' => $phone]);
    Checkin::factory()->for(Pass::factory()->for($attendee)->create())->create(['direction' => CheckinDirection::In]);

    return $attendee;
}

it('saves an exhibition as its own event type', function () {
    expect(Event::factory()->create(['type' => EventType::Exhibition])->fresh()->type)->toBe(EventType::Exhibition);
});

it('tells the scanner how many of the organizer\'s earlier events each pass holder came to', function () {
    $owner = User::factory()->create();
    $event = eventBy($owner, ['starts_at' => '2026-11-14 10:00']);
    foreach (['2026-03-01', '2026-07-01', '2026-12-20'] as $date) { // the December one is later, not earlier
        Attendee::factory()->for(eventBy($owner, ['starts_at' => $date]))->create(['phone' => '9824017356']);
    }
    Attendee::factory()->for(eventBy(User::factory()->create(), ['starts_at' => '2026-01-01']))->create(['phone' => '9824017356']);
    Pass::factory()->for(Attendee::factory()->for($event)->create(['phone' => '9824017356']))->create();
    $this->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);

    $this->getJson(route('scan.bundle'))->assertOk()->assertJsonPath('passes.0.earlier_events', 2);
});

it('splits attendees into first-timers and returning visitors on the report', function () {
    $owner = User::factory()->create();
    $previous = eventBy($owner, ['name' => 'Spring Home Expo', 'starts_at' => '2026-03-01']);
    $older = eventBy($owner, ['starts_at' => '2026-01-01']);
    $event = eventBy($owner, ['starts_at' => '2026-11-14']);
    $event->members()->attach($owner->id, ['role' => MemberRole::Organizer]);
    Attendee::factory()->for($previous)->create(['phone' => '9000000001']);
    Attendee::factory()->for($previous)->create(['phone' => '9000000002']);
    Attendee::factory()->for($previous)->create(['phone' => '9000000003']);
    Attendee::factory()->for($older)->create(['phone' => '9000000002']);
    attendedBy($event, '9000000001');
    attendedBy($event, '9000000002');
    attendedBy($event, '9000000009');
    Attendee::factory()->for($event)->create(['phone' => '9000000003']); // registered, never came in

    $this->actingAs($owner)->get(route('print.report', $event))
        ->assertOk()
        ->assertSeeInOrder(['First time at one of your events', '1', '33%', 'Came to 1 earlier event', '1', '33%', 'Came to 2 or more earlier events', '1', '33%'])
        ->assertSee('2 of the 3 people registered for Spring Home Expo came again');
});

it('leaves returning visitors off the report of an organizer\'s first event', function () {
    $owner = User::factory()->create();
    $event = eventBy($owner);
    $event->members()->attach($owner->id, ['role' => MemberRole::Organizer]);
    attendedBy($event, '9000000001');

    $this->actingAs($owner)->get(route('print.report', $event))->assertOk()->assertDontSee('New and returning visitors');
});
