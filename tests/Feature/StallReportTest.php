<?php

use App\Enums\CheckinDirection;
use App\Models\Attendee;
use App\Models\Checkin;
use App\Models\Pass;
use App\Models\Stall;
use App\Models\User;

it('shows a stall its footfall and leads without contact details', function () {
    $event = actingAsOrganizer();
    $stall = Stall::factory()->for($event)->create(['name' => 'Kesar Kulfi', 'view_count' => 40]);
    Stall::factory()->for($event)->create(['view_count' => 90]);
    foreach (['9800000001', '9800000002'] as $phone) {
        $attendee = Attendee::factory()->for($event)->create(['phone' => $phone]);
        Checkin::factory()->for(Pass::factory()->for($attendee)->create())->create(['direction' => CheckinDirection::In]);
        $stall->leads()->create(['attendee_id' => $attendee->id]);
    }
    Checkin::factory()->for(Pass::factory()->for(Attendee::factory()->for($event))->create())->create(['direction' => CheckinDirection::In]);

    $this->get(route('print.stall', [$event, $stall]))
        ->assertOk()
        ->assertSee('Kesar Kulfi')
        ->assertSeeInOrder(['40', 'stall page opens'])
        ->assertSee('5% of page opens')
        ->assertSee('67% became leads')
        ->assertSee('#2')
        ->assertDontSee('9800000001');
});

it('does not show another event\'s stall', function () {
    $theirs = Stall::factory()->create();
    $event = actingAsOrganizer();

    $this->get(route('print.stall', [$event, $theirs]))->assertNotFound();
});

it('keeps stall reports and the leads CSV to the event\'s organizers', function () {
    $event = actingAsOrganizer();
    $stall = Stall::factory()->for($event)->create();

    $this->actingAs(User::factory()->create());
    $this->get(route('print.stall', [$event, $stall]))->assertForbidden();
    $this->get(route('print.leads.csv', $event))->assertForbidden();
});

it('exports every stall\'s leads in one CSV', function () {
    $event = actingAsOrganizer();
    $chai = Stall::factory()->for($event)->create(['name' => 'Chai Point']);
    $kulfi = Stall::factory()->for($event)->create(['name' => 'Kesar Kulfi']);
    $aarti = Attendee::factory()->for($event)->create(['name' => 'Aarti Shah', 'phone' => '9800000001']);
    $chai->leads()->create(['attendee_id' => $aarti->id, 'note' => 'Wants catering']);
    $kulfi->leads()->create(['attendee_id' => $aarti->id]);

    $csv = $this->get(route('print.leads.csv', $event));

    $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $body = $csv->streamedContent();
    expect($body)
        ->toContain('Stall,Name,Phone,Email,Note,"Captured at"')
        ->toContain('"Chai Point","Aarti Shah",9800000001,,"Wants catering"')
        ->toContain('"Kesar Kulfi","Aarti Shah",9800000001')
        ->and(substr_count($body, "\n"))->toBe(3);
});
