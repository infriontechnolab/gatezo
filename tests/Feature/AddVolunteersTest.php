<?php

use App\Filament\Pages\Volunteers;
use App\Filament\Resources\Shifts\Pages\EditShift;
use App\Filament\Resources\Shifts\Pages\ListShifts;
use App\Models\Gate;
use App\Models\Shift;
use App\Support\Phone;
use App\Support\VolunteerList;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('reads a volunteer list the way it comes out of WhatsApp or Excel', function () {
    $list = VolunteerList::parse("1) Ravi Patel, +91 98240 17351\nMeera Shah\t9824017352\n\n- Nirav Desai\nravi patel\nKunal Joshi 12345\n9824017353\n");

    expect($list['people'])->toBe([
        ['name' => 'Ravi Patel', 'phone' => '9824017351'],
        ['name' => 'Meera Shah', 'phone' => '9824017352'],
        ['name' => 'Nirav Desai', 'phone' => null],
        ['name' => 'Kunal Joshi', 'phone' => null],
    ])->and($list['errors'])->toHaveCount(2); // Kunal's short number, and a line with only a number
});

it('puts a pasted list on the roster at one post and time', function () {
    $event = actingAsOrganizer();
    $gate = Gate::factory()->for($event)->create(['name' => 'Main Gate']);

    Livewire::test(Volunteers::class)
        ->callAction('addVolunteers', data: [
            'people' => "Ravi Patel, 98240 17351\nMeera Shah",
            'gate_id' => $gate->id,
            'starts_at' => '2026-10-10 18:00',
            'ends_at' => '2026-10-10 22:00',
            'label' => 'Entry',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('2 volunteers added');

    $ravi = $event->shifts()->where('volunteer_name', 'Ravi Patel')->sole();
    expect($ravi)->phone->toBe('9824017351')->gate_id->toBe($gate->id)->label->toBe('Entry')->invite_token->not->toBeNull()
        ->and($ravi->starts_at->format('Y-m-d H:i'))->toBe('2026-10-10 18:00')
        ->and($event->shifts()->where('volunteer_name', 'Meera Shah')->sole()->phone)->toBeNull();
});

it('does not double the roster when the same list is pasted again', function () {
    $event = actingAsOrganizer();
    $data = ['people' => "Ravi Patel\nMeera Shah", 'starts_at' => '2026-10-10 18:00'];

    Livewire::test(ListShifts::class)->callAction('addVolunteers', data: $data)->assertNotified('2 volunteers added');
    Livewire::test(ListShifts::class)
        ->callAction('addVolunteers', data: ['people' => "Ravi Patel, 9824017351\nMeera Shah"] + $data)
        ->assertNotified('0 volunteers added, 2 already on the roster');

    expect($event->shifts()->count())->toBe(2)
        ->and($event->shifts()->where('volunteer_name', 'Ravi Patel')->sole()->phone)->toBe('9824017351'); // the new phone is kept
});

it('opens the volunteer\'s own WhatsApp chat with their link', function () {
    $event = actingAsOrganizer();
    $withPhone = Shift::factory()->for($event)->create(['volunteer_name' => 'Ravi Patel', 'phone' => '9824017351']);
    $withoutPhone = Shift::factory()->for($event)->create(['volunteer_name' => 'Meera Shah', 'phone' => null]);

    Livewire::test(ListShifts::class)
        ->assertActionVisible(TestAction::make('whatsapp')->table($withPhone))
        ->assertActionHidden(TestAction::make('whatsapp')->table($withoutPhone))
        ->assertActionHasUrl(TestAction::make('whatsapp')->table($withPhone), Phone::whatsappUrl('9824017351', "Hi Ravi Patel, you're volunteering at {$event->name} (anywhere".($withPhone->starts_at ? ', '.$withPhone->starts_at->format('D g:i A') : '')."). Open this on the phone you'll scan with: {$withPhone->inviteUrl()}"));

    expect(Phone::whatsappUrl('9824017351', 'Hi'))->toBe('https://wa.me/919824017351?text=Hi')
        ->and(Phone::whatsappUrl(null, 'Hi'))->toBe('https://wa.me/?text=Hi');
});

it('keeps a volunteer\'s phone in one shape when edited', function () {
    $event = actingAsOrganizer();
    $shift = Shift::factory()->for($event)->create(['volunteer_name' => 'Ravi Patel']);

    Livewire::test(EditShift::class, ['record' => $shift->getRouteKey()])
        ->fillForm(['phone' => '+91 98240 17351'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($shift->fresh()->phone)->toBe('9824017351');
});
