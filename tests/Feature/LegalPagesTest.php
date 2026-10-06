<?php

use App\Filament\Auth\Register;
use App\Models\Event;
use Livewire\Livewire;

it('serves the privacy policy and terms with the contact address, open to search engines', function (string $route, string $heading) {
    config(['gatezo.contact_email' => 'hello@gatezo.in']);

    $this->get(route($route))
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('mailto:hello@gatezo.in', false)
        ->assertDontSee('name="robots"', false);
})->with([
    'privacy' => ['privacy', 'Privacy policy'],
    'terms' => ['terms', 'Terms of use'],
]);

it('links both pages from the landing page footer', function () {
    $this->get(route('landing'))->assertOk()->assertSee(route('privacy'), false)->assertSee(route('terms'), false);
});

it('links the privacy policy from an event\'s registration form', function () {
    $this->get(route('event.show', Event::factory()->create()))->assertOk()->assertSee(route('privacy'), false);
});

it('shows the terms and privacy policy on organizer sign-up', function () {
    Livewire::test(Register::class)->assertSee(route('terms'), false)->assertSee(route('privacy'), false);
});
