<?php

use App\Enums\MemberRole;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Signs in an organizer on their own event and points the admin panel at it. From here on
 * every tenant-owned record created gets this event, so make other events' records first.
 */
function actingAsOrganizer(): Event
{
    $organizer = User::factory()->create();
    $event = Event::factory()->create();
    $event->forceFill(['created_by' => $organizer->id])->save();
    $event->members()->attach($organizer->id, ['role' => MemberRole::Organizer]);

    test()->actingAs($organizer);
    // Panel middleware normally does this; booting registers the tenant scopes and the
    // observer that fills event_id on create, which Livewire tests would otherwise skip.
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    Filament::setTenant($event = $event->fresh(), isQuiet: true);

    return $event;
}
