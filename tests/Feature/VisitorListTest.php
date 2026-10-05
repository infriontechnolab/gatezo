<?php

use App\Enums\MemberRole;
use App\Filament\Pages\Tenancy\EditEventProfile;
use App\Filament\Pages\Visitors;
use App\Filament\Resources\Attendees\Pages\CreateAttendee;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function joinScannerOf(Event $event): void
{
    test()->post(route('scan.join.post'), ['code' => $event->volunteer_code, 'name' => 'Ravi']);
}

describe('asking at registration', function () {
    it('shows the WhatsApp checkbox only on events that ask', function () {
        $this->get(route('event.show', Event::factory()->create()))->assertOk()->assertDontSee('name="marketing_opt_in"', false);
        $this->get(route('event.show', Event::factory()->create(['ask_marketing_opt_in' => true])))->assertOk()->assertSee('name="marketing_opt_in"', false);
    });

    it('records a yes from the form and adds them to the owner\'s visitors', function () {
        $owner = User::factory()->create();
        $event = eventBy($owner, ['ask_marketing_opt_in' => true]);

        $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '98240 17356', 'marketing_opt_in' => '1'])->assertRedirect();

        expect($event->attendees()->sole())
            ->marketing_opt_in->toBeTrue()
            ->marketing_opt_in_at->not->toBeNull();
        expect(Visitor::sole())
            ->user_id->toBe($owner->id)
            ->phone->toBe('9824017356')
            ->marketing_opt_in->toBeTrue();
    });

    it('records an unticked box as a no', function () {
        $event = eventBy(User::factory()->create(), ['ask_marketing_opt_in' => true]);

        $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '9824017356'])->assertRedirect();

        expect($event->attendees()->sole()->marketing_opt_in)->toBeFalse();
    });

    it('ignores a posted yes when the event does not ask', function () {
        $event = eventBy(User::factory()->create());

        $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '9824017356', 'marketing_opt_in' => '1'])->assertRedirect();

        expect($event->attendees()->sole()->marketing_opt_in)->toBeNull()
            ->and(Visitor::sole()->marketing_opt_in)->toBeFalse();
    });

    it('takes the newer answer when someone registers again for their pass', function () {
        $event = eventBy(User::factory()->create(), ['ask_marketing_opt_in' => true]);
        $this->post(route('event.register', $event), ['name' => 'Aarti Shah', 'phone' => '9824017356']);

        $this->post(route('event.register', $event), ['name' => 'Aarti', 'phone' => '9824017356', 'marketing_opt_in' => '1'])
            ->assertSessionHas('existing_pass');

        expect($event->attendees()->sole()->marketing_opt_in)->toBeTrue();
    });

    it('records a yes from a volunteer walk-up', function () {
        $event = eventBy(User::factory()->create(), ['ask_marketing_opt_in' => true]);
        joinScannerOf($event);

        $this->postJson(route('scan.walkup'), ['name' => 'Meera Joshi', 'phone' => '9824017356', 'marketing_opt_in' => true])->assertOk();

        expect($event->attendees()->sole()->marketing_opt_in)->toBeTrue();
    });
});

describe('keeping the list', function () {
    it('keeps one visitor per phone across the owner\'s events, separate from other organizers', function () {
        $owner = User::factory()->create();
        $first = eventBy($owner);
        $second = eventBy($owner);
        $someoneElses = eventBy(User::factory()->create());

        $this->travelTo(now()->subMonth());
        Attendee::factory()->for($first)->create(['name' => 'Meera', 'phone' => '9824017356']);
        $this->travelBack();
        Attendee::factory()->for($second)->create(['name' => 'Meera Joshi', 'phone' => '+91 98240 17356']);
        Attendee::factory()->for($someoneElses)->create(['name' => 'Meera Joshi', 'phone' => '9824017356']);

        expect(Visitor::where('user_id', $owner->id)->sole())
            ->name->toBe('Meera Joshi')
            ->events_count->toBe(2)
            ->last_event_id->toBe($second->id);
        expect(Visitor::where('user_id', '!=', $owner->id)->sole()->events_count)->toBe(1);
    });

    it('withdraws an earlier yes when they say no at a later event', function () {
        $owner = User::factory()->create();
        Attendee::factory()->for(eventBy($owner))->create(['phone' => '9824017356', 'marketing_opt_in' => true]);
        $this->travel(1)->day();

        Attendee::factory()->for(eventBy($owner))->create(['phone' => '9824017356', 'marketing_opt_in' => false]);

        expect(Visitor::sole()->marketing_opt_in)->toBeFalse();
    });

    it('keeps an earlier yes when a later event did not ask', function () {
        $owner = User::factory()->create();
        Attendee::factory()->for(eventBy($owner))->create(['phone' => '9824017356', 'marketing_opt_in' => true]);
        $this->travel(1)->day();

        Attendee::factory()->for(eventBy($owner))->create(['phone' => '9824017356']);

        expect(Visitor::sole()->marketing_opt_in)->toBeTrue();
    });

    it('moves a visitor when their phone is corrected', function () {
        $attendee = Attendee::factory()->for(eventBy(User::factory()->create()))->create(['phone' => '9824017356']);

        $attendee->update(['phone' => '9824017357']);

        expect(Visitor::pluck('phone')->all())->toBe(['9824017357']);
    });

    it('recounts visitors when one of the owner\'s events is deleted', function () {
        $owner = User::factory()->create();
        $kept = eventBy($owner);
        $deleted = eventBy($owner);
        Attendee::factory()->for($kept)->create(['phone' => '9824017356']);
        Attendee::factory()->for($deleted)->create(['phone' => '9824017356']);
        Attendee::factory()->for($deleted)->create(['phone' => '9999900000']);

        $deleted->delete();

        expect(Visitor::sole())->phone->toBe('9824017356')->events_count->toBe(1);
    });
});

describe('Visitors page', function () {
    it('lists only the signed-in organizer\'s own visitors', function () {
        $otherOwner = User::factory()->create();
        Attendee::factory()->for(eventBy($otherOwner))->create(['name' => 'Not Yours', 'phone' => '9000000001']);
        $event = actingAsOrganizer();
        $event->members()->attach($otherOwner->id, ['role' => MemberRole::Organizer]); // they help on this event, but their list stays theirs
        Attendee::factory()->for($event)->create(['name' => 'Aarti Shah', 'phone' => '9824017356']);

        Livewire::test(Visitors::class)
            ->assertOk()
            ->assertCanSeeTableRecords(Visitor::where('user_id', auth()->id())->get())
            ->assertCanNotSeeTableRecords(Visitor::where('user_id', $otherOwner->id)->get());
    });

    it('exports only opted-in numbers, in WhatsApp format', function () {
        $event = actingAsOrganizer();
        Attendee::factory()->for($event)->create(['name' => 'Aarti Shah', 'phone' => '9824017356', 'marketing_opt_in' => true]);
        Attendee::factory()->for($event)->create(['name' => 'Ravi Patel', 'phone' => '9824017357', 'marketing_opt_in' => false]);

        $csv = Livewire::test(Visitors::class)->callAction('export')->effects['download']['content'];

        expect(base64_decode($csv))
            ->toContain('"Aarti Shah",919824017356')
            ->not->toContain('Ravi Patel');
    });

    it('stops messages to a visitor and keeps them stopped after their details change', function () {
        $event = actingAsOrganizer();
        $attendee = Attendee::factory()->for($event)->create(['name' => 'Aarti Shah', 'phone' => '9824017356', 'marketing_opt_in' => true]);

        Livewire::test(Visitors::class)->callTableAction('optOut', Visitor::sole());
        $attendee->refresh()->update(['name' => 'Aarti S. Shah']);

        expect(Visitor::sole()->marketing_opt_in)->toBeFalse()
            ->and(DB::table('attendees')->value('marketing_opt_in'))->toBe(0);
    });

    it('writes the invite message for the event that is open', function () {
        $event = actingAsOrganizer();
        $event->update(['name' => 'Rajkot Home Expo', 'venue' => 'Race Course Ground', 'starts_at' => '2026-11-14 10:00']);

        expect(Livewire::test(Visitors::class)->instance()->inviteMessage('Aarti'))
            ->toContain('Hi Aarti! Rajkot Home Expo is on Sat 14 Nov, Race Course Ground.')
            ->toContain(route('event.show', $event));
    });
});

it('lets the organizer turn the question on', function () {
    $event = actingAsOrganizer();

    Livewire::test(EditEventProfile::class)
        ->fillForm(['ask_marketing_opt_in' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->fresh()->ask_marketing_opt_in)->toBeTrue();
});

it('records a yes entered by hand in the panel', function () {
    actingAsOrganizer();

    Livewire::test(CreateAttendee::class)
        ->fillForm(['name' => 'Aarti Shah', 'phone' => '9824017356', 'marketing_opt_in' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Visitor::sole()->marketing_opt_in)->toBeTrue();
});
