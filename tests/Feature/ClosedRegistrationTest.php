<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Pass;

function closedEventWith(array $attendee): array
{
    $event = Event::factory()->create(['allow_self_register' => false]);
    $pass = Pass::factory()->for(Attendee::factory()->for($event)->create($attendee))->create();

    return [$event, $pass];
}

it('lets people on an imported list collect their pass when registration is closed', function () {
    [$event, $pass] = closedEventWith(['name' => 'Aarti Shah', 'phone' => '9824017351', 'email' => 'aarti@example.com']);

    $this->get(route('event.show', $event))->assertOk()
        ->assertSee('New registrations are closed')
        ->assertSee('action="'.route('event.find', $event).'"', false);

    $this->post(route('event.find', $event), ['name' => 'Aarti', 'contact' => '+91 98240 17351'])
        ->assertRedirect(route('pass.show', $pass))->assertSessionHas('existing_pass', true);
    $this->post(route('event.find', $event), ['name' => 'aarti shah', 'contact' => 'Aarti@Example.com'])
        ->assertRedirect(route('pass.show', $pass));
});

it('never registers anyone new while registration is closed', function () {
    [$event] = closedEventWith(['name' => 'Aarti Shah', 'phone' => '9824017351']);

    $this->from(route('event.show', $event))
        ->post(route('event.find', $event), ['name' => 'Bina Rao', 'contact' => '9824017352'])
        ->assertRedirect(route('event.show', $event))
        ->assertSessionHasErrors('contact')
        ->assertSessionHasInput('name', 'Bina Rao');
    $this->post(route('event.register', $event), ['name' => 'Bina Rao', 'phone' => '9824017352'])->assertForbidden();

    expect($event->attendees()->count())->toBe(1);
});

it('does not hand a pass to someone who only knows the number', function () {
    [$event] = closedEventWith(['name' => 'Aarti Shah', 'phone' => '9824017351']);

    $this->post(route('event.find', $event), ['name' => 'Kunal', 'contact' => '9824017351'])->assertSessionHasErrors('contact');
});

it('prints a poster and gate signs that match how the event is set up', function () {
    $event = actingAsOrganizer();
    $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);

    $this->get(route('print.kit', $event))->assertOk()
        ->assertSee('Scan to get your entry pass')->assertSee('Free entry')
        ->assertSee('join with the event code');

    $event->update(['allow_self_register' => false, 'join_by_code' => false]);

    $this->get(route('print.kit', $event))->assertOk()
        ->assertSee('Already registered? Scan for your pass')->assertDontSee('Free entry')
        ->assertSee('personal scanner link the organizer sent you')->assertDontSee('join with the event code');
});
