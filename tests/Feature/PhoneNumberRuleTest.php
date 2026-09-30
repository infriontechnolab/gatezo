<?php

use App\Models\Event;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;

$passes = fn (string $phone): bool => Validator::make(['phone' => $phone], ['phone' => [new PhoneNumber]])->passes();

it('refuses placeholder and impossible numbers', function (string $phone) use ($passes) {
    expect($passes($phone))->toBeFalse();
})->with([
    'all zeros' => '0000000000',
    'one digit repeated' => '9999999999',
    'counting up' => '1234567890',
    'counting down' => '9876543210',
    'counting down with +91' => '+91 98765 43210',
    'Indian number starting with 5' => '5823456710',
    'Indian number starting with 1' => '1823456710',
]);

it('accepts real-looking numbers', function (string $phone) use ($passes) {
    expect($passes($phone))->toBeTrue();
})->with([
    'plain mobile' => '9823456710',
    'with +91 and spaces' => '+91 98234 56710',
    'with a leading 0' => '09823456710',
    'starting with 6' => '6123456780',
    'UK number with country code' => '+44 7911 123456',
    'US number with country code' => '+1 415 555 0132',
]);

it('stops a placeholder number at registration', function () {
    $event = Event::factory()->create(['allow_self_register' => true]);

    $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '0000000000'])
        ->assertSessionHasErrors('phone')
        ->assertSessionHasInput(['name' => 'Aarti Shah', 'phone' => '0000000000']);

    expect($event->attendees()->count())->toBe(0);
});
