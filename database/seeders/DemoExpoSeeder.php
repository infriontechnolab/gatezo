<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Enums\KitStyle;
use App\Enums\MemberRole;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;
use App\Services\VisitorBook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The demo organizer also runs a home expo three times a year: two shows done, the next one
 * taking registrations. Visitors come back from show to show (and some from the garba), so
 * the Visitors page, "Visit #3" on the scanner and the returning-visitors report have
 * something to show. Called from DemoSeeder after the garba, sharing its seeded RNG.
 */
class DemoExpoSeeder extends Seeder
{
    private const FIRST = ['Aarav', 'Aditi', 'Alpa', 'Amit', 'Bhavna', 'Chetan', 'Daksha', 'Dinesh', 'Hema', 'Hitesh', 'Jagdish', 'Jigna', 'Kalpesh', 'Komal', 'Manish', 'Minal', 'Mukesh', 'Neha', 'Paresh', 'Pinky', 'Rakesh', 'Rekha', 'Sejal', 'Sunil', 'Trupti', 'Vipul'];

    private const LAST = ['Shah', 'Patel', 'Mehta', 'Joshi', 'Desai', 'Parikh', 'Kotak', 'Sheth', 'Doshi', 'Vora', 'Gajjar', 'Mistry', 'Lakhani', 'Dholakia'];

    public const SLUGS = ['home-expo-spring', 'home-expo-monsoon', 'home-expo-diwali'];

    public function run(User $organizer): void
    {
        Event::whereIn('slug', self::SLUGS)->get()->each->delete();

        $now = now();
        $shows = [
            // slug, name, starts, registrations, share that are back from the previous shows, share that came in
            [self::SLUGS[0], 'Rajkot Home & Lifestyle Expo · Spring', $now->copy()->subMonths(7)->startOfWeek()->addDays(5)->setTime(10, 0), 640, 0.0, 0.82],
            [self::SLUGS[1], 'Rajkot Home & Lifestyle Expo · Monsoon', $now->copy()->subMonths(3)->startOfWeek()->addDays(5)->setTime(10, 0), 780, 0.34, 0.79],
            [self::SLUGS[2], 'Rajkot Home & Lifestyle Expo · Diwali', $now->copy()->addWeeks(5)->startOfWeek()->addDays(5)->setTime(10, 0), 260, 0.55, 0.0],
        ];
        // A few garba-goers turn up at the expo too: same organizer, same visitor list.
        $garbaGoers = DB::table('attendees')->join('events', 'events.id', '=', 'attendees.event_id')
            ->where('events.slug', 'sharad-utsav')->inRandomOrder(7)->limit(90)->pluck('attendees.name', 'attendees.phone')->all();
        $codes = [];

        $seen = [];   // phone => name, everyone from the shows so far
        $serial = 0;
        foreach ($shows as $k => [$slug, $name, $startsAt, $count, $returningShare, $arrivedShare]) {
            $event = Event::create([
                'slug' => $slug,
                'name' => $name,
                'type' => EventType::Exhibition,
                'description' => '120 stalls of furniture, kitchenware, home decor and appliances. Free entry, a guaranteed gift for every registered visitor.',
                'venue' => 'Race Course Ground, Rajkot',
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->setTime(21, 0),
                'ask_marketing_opt_in' => true,
                'goodies_enabled' => true,
                'goodies_name' => 'Gift',
                'accent_hex' => '#1F6F78',
                'kit_style' => KitStyle::Bold,
            ]);
            $event->forceFill(['created_by' => $organizer->id, 'created_at' => min($startsAt->copy()->subWeeks(4), $now->copy()->subDays(12))])->save();
            $event->members()->attach($organizer->id, ['role' => MemberRole::Organizer]);
            $gate = $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']);
            $giftCounter = $event->gates()->create(['name' => 'Gift counter', 'code' => 'C1', 'is_entry' => false, 'is_goodies' => true]);

            $people = [];
            $returning = array_keys($seen);
            shuffle($returning);
            foreach (array_slice($returning, 0, (int) round($count * $returningShare)) as $phone) {
                $people[$phone] = $seen[$phone];
            }
            $people += $k === 1 ? array_slice($garbaGoers, 0, 50, true) : ($k === 2 ? array_slice($garbaGoers, 50, null, true) : []);
            while (count($people) < $count) {
                $phone = '7'.str_pad((string) (($serial++ * 7919 + 13) % 1000000000), 9, '0', STR_PAD_LEFT);
                $people[$phone] = self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
            }

            // The next show opened registration ten days ago.
            $registrationOpens = $startsAt->isFuture() ? $now->copy()->subDays(10) : $startsAt->copy()->subWeeks(3);
            $rows = [];
            foreach ($people as $phone => $person) {
                $created = $startsAt->isFuture()
                    ? $registrationOpens->copy()->addMinutes(mt_rand(0, (int) $registrationOpens->diffInMinutes($now)))
                    : $registrationOpens->copy()->addMinutes(mt_rand(0, 21 * 24 * 60));
                $answer = mt_rand(0, 99) < 46; // about half say yes to WhatsApp news
                $rows[] = [
                    'event_id' => $event->id, 'name' => $person, 'phone' => $phone, 'ticket_type' => 'general', 'is_vip' => false,
                    'source' => 'online', 'marketing_opt_in' => $answer, 'marketing_opt_in_at' => $created,
                    'created_at' => $created, 'updated_at' => $created,
                ];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('attendees')->insert($chunk);
            }
            $seen += $people;

            $passRows = [];
            foreach ($event->attendees()->orderBy('id')->pluck('id') as $attendeeId) {
                do {
                    $code = Pass::generateCode(); // checks the table, but not this batch
                } while (isset($codes[$code]));
                $codes[$code] = true;
                $passRows[] = ['event_id' => $event->id, 'attendee_id' => $attendeeId, 'code' => $code, 'revoked' => false, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($passRows, 500) as $chunk) {
                DB::table('passes')->insert($chunk);
            }

            $checkins = [];
            $handouts = [];
            foreach ($event->passes()->inRandomOrder(11 + $k)->limit((int) round($count * $arrivedShare))->pluck('id') as $passId) {
                $at = $startsAt->copy()->addMinutes(mt_rand(0, 600));
                $checkins[] = ['event_id' => $event->id, 'pass_id' => $passId, 'gate_id' => $gate->id, 'direction' => 'in', 'scanned_at' => $at, 'synced_at' => $at, 'client_id' => (string) Str::uuid(), 'duplicate_flag' => false];
                $handouts[] = ['event_id' => $event->id, 'pass_id' => $passId, 'gate_id' => $giftCounter->id, 'scanned_at' => $at->copy()->addMinutes(2), 'synced_at' => $at->copy()->addMinutes(2), 'client_id' => (string) Str::uuid()];
            }
            foreach (array_chunk($checkins, 500) as $chunk) {
                DB::table('checkins')->insert($chunk);
            }
            foreach (array_chunk($handouts, 500) as $chunk) {
                DB::table('handouts')->insert($chunk);
            }
        }

        // Rows above skip model events, so build the visitor list in one go.
        app(VisitorBook::class)->rebuild($organizer->id);
    }
}
