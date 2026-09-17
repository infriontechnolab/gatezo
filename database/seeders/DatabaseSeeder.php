<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local dev seed: one organizer, one event with three gates, a handful of attendees.
 * Login: organizer@gatezo.local / password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $organizer = User::factory()->create([
            'name' => 'Demo Organizer',
            'email' => 'organizer@gatezo.local',
            'password' => 'password',
        ]);

        $event = Event::create([
            'slug' => 'demo-garba',
            'name' => 'Demo Garba Night',
            'type' => 'festival',
            'venue' => 'Society Ground, Ahmedabad',
            'capacity' => 2000,
            'starts_at' => now()->addDays(7)->setTime(19, 30),
            'ends_at' => now()->addDays(7)->setTime(23, 30),
            'allow_reentry' => true,
        ]);
        $event->forceFill(['created_by' => $organizer->id, 'volunteer_code' => '123456'])->save();
        $event->members()->attach($organizer->id, ['role' => 'organizer']);

        foreach ([['Main Gate', 'G1'], ['Side Gate', 'G2'], ['VIP Entry', 'G3']] as [$name, $code]) {
            $event->gates()->create(['name' => $name, 'code' => $code]);
        }
        $event->gates()->create(['name' => 'Food Court', 'code' => 'Z1', 'is_entry' => false]);

        foreach (['Aarti Shah', 'Bhavesh Patel', 'Chirag Mehta', 'Dipti Joshi', 'Esha Trivedi'] as $i => $name) {
            $attendee = $event->attendees()->create([
                'name' => $name,
                'phone' => '98'.str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'is_vip' => $i === 0,
                'ticket_type' => $i === 0 ? 'vip' : 'general',
            ]);
            $attendee->pass()->create(['event_id' => $event->id]);
        }
    }
}
