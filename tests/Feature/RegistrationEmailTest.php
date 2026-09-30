<?php

use App\Filament\Pages\Tenancy\EditEventProfile;
use App\Models\Event;
use Livewire\Livewire;

it('asks for email only when the organizer turned it on', function () {
    $this->get(route('event.show', Event::factory()->create()))->assertOk()->assertDontSee('name="email"', false);
    $this->get(route('event.show', Event::factory()->create(['ask_email' => true])))->assertOk()->assertSee('name="email"', false);
});

it('keeps the email a registrant gives', function () {
    $event = Event::factory()->create(['ask_email' => true]);

    $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '9824017356', 'email' => 'aarti@example.com'])
        ->assertRedirect();

    expect($event->attendees()->sole()->email)->toBe('aarti@example.com');
});

it('rejects an email that is not an address', function () {
    $event = Event::factory()->create(['ask_email' => true]);

    $this->from(route('event.show', $event))
        ->post(route('event.register', $event), ['name' => 'Aarti Shah', 'email' => 'not-an-email'])
        ->assertSessionHasErrors('email')
        ->assertSessionHasInput('name', 'Aarti Shah');
});

it('lets the organizer turn on asking for email', function () {
    $event = actingAsOrganizer();

    Livewire::test(EditEventProfile::class)
        ->fillForm(['ask_email' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->ask_email)->toBeTrue();
});
