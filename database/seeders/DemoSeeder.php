<?php

namespace Database\Seeders;

use App\Models\Draw;
use App\Models\Event;
use App\Models\Pass;
use App\Models\User;
use App\Services\DrawEngine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A realistic, finished-looking event for demos: 1,500 registrations over two weeks,
 * ~1,100 arrivals on a garba-shaped curve tonight, re-entries, stalls with scans and
 * leads, a volunteer roster with duty logs, feedback, one finished lucky draw and one
 * ready to run. Deterministic (seeded RNG) so screenshots are repeatable.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Login: demo@gatezo.local / password · event slug: sharad-utsav · volunteer code 246810
 */
class DemoSeeder extends Seeder
{
    private const FIRST = ['Aarav', 'Aditi', 'Akash', 'Ananya', 'Anjali', 'Arjun', 'Bhavesh', 'Bhoomi', 'Chirag', 'Darshan', 'Deepa', 'Dhruv', 'Dipti', 'Esha', 'Falguni', 'Gaurav', 'Hardik', 'Harsha', 'Hetal', 'Ishaan', 'Jay', 'Jinal', 'Kajal', 'Karan', 'Kinjal', 'Krishna', 'Kunal', 'Leena', 'Manan', 'Meera', 'Mihir', 'Nidhi', 'Nikita', 'Nirav', 'Parth', 'Pooja', 'Prachi', 'Priya', 'Rahul', 'Rajvi', 'Ravi', 'Riya', 'Rohan', 'Sagar', 'Sanjay', 'Shreya', 'Siddharth', 'Sneha', 'Tejas', 'Urvi', 'Vaibhav', 'Vishal', 'Yash', 'Zeel'];

    private const LAST = ['Shah', 'Patel', 'Mehta', 'Joshi', 'Trivedi', 'Desai', 'Rana', 'Modi', 'Vyas', 'Parikh', 'Bhatt', 'Soni', 'Gandhi', 'Thakkar', 'Panchal', 'Dave', 'Pandya', 'Solanki', 'Chauhan', 'Prajapati', 'Vaghela', 'Jadeja', 'Parmar', 'Raval'];

    private const COMMENTS = [
        5 => ['Best garba in the society so far!', 'Loved the live band. Please repeat next year.', 'Entry was so quick this time, no line at all.', 'Kids had a great time, the food court was excellent.', 'Perfect arrangement, volunteers were very helpful.', 'The dandiya round at 10 was unreal.'],
        4 => ['Great night, sound was a little loud near the stage.', 'Good crowd, parking could be better managed.', 'Enjoyed it, the chai stall ran out early though.', 'Nice decor. Water counters needed on the far side.'],
        3 => ['Okay overall. Too crowded near Gate 1 around 9.', 'Music selection was repetitive after 10:30.', 'Fine, but the prize draw took too long.'],
        2 => ['Parking was a mess, took 25 minutes to get out.', 'Very long wait at the food court.'],
        1 => ['Sound system failed twice.'],
    ];

    public function run(): void
    {
        mt_srand(20260917);

        if ($old = Event::where('slug', 'sharad-utsav')->first()) {
            $old->delete();
        }
        User::where('email', 'like', '%@demo.gatezo.local')->delete();

        $organizer = User::firstOrCreate(['email' => 'demo@gatezo.local'], ['name' => 'Bhavesh Patel', 'password' => 'password']);
        $co = User::firstOrCreate(['email' => 'hetal@gatezo.local'], ['name' => 'Hetal Modi', 'password' => 'password']);

        $tonight = Carbon::today()->setTime(19, 30);
        $now = now();
        // If it's before the event tonight, pretend it happened yesterday so "live" numbers exist.
        if ($now->lt($tonight->copy()->addHours(2))) {
            $tonight->subDay();
        }

        $event = Event::create([
            'slug' => 'sharad-utsav',
            'name' => 'Sharad Utsav Garba 2026',
            'type' => 'festival',
            'description' => 'Nine nights of garba by Shivalik Residency. Free entry for residents and guests.',
            'venue' => 'Shivalik Residency Ground, Ahmedabad',
            'capacity' => 1500,
            'starts_at' => $tonight,
            'ends_at' => $tonight->copy()->setTime(23, 30),
            'allow_reentry' => true,
        ]);
        $event->forceFill(['created_by' => $organizer->id, 'volunteer_code' => '246810', 'created_at' => $now->copy()->subDays(21)])->save();
        $event->members()->attach([$organizer->id => ['role' => 'organizer'], $co->id => ['role' => 'organizer']]);

        $gates = collect([
            $event->gates()->create(['name' => 'Main Gate', 'code' => 'G1']),
            $event->gates()->create(['name' => 'Tower B Gate', 'code' => 'G2']),
            $event->gates()->create(['name' => 'VIP Entry', 'code' => 'G3']),
        ]);
        $zones = collect([
            $event->gates()->create(['name' => 'Food Court', 'code' => 'Z1', 'is_entry' => false]),
            $event->gates()->create(['name' => 'Stage', 'code' => 'Z2', 'is_entry' => false]),
            $event->gates()->create(['name' => 'Parking', 'code' => 'Z3', 'is_entry' => false]),
        ]);

        // ---- Stalls ----------------------------------------------------------------
        $stalls = collect([
            ['Chai Point', 'Row A · Stall 1', 'Free cutting chai with this page', [['name' => 'Cutting chai', 'price' => 15], ['name' => 'Masala chai', 'price' => 25], ['name' => 'Vada pav', 'price' => 30]], 412],
            ['Kesar Kulfi', 'Row A · Stall 4', null, [['name' => 'Kesar kulfi', 'price' => 60], ['name' => 'Malai kulfi', 'price' => 50]], 278],
            ['Rajwadi Chaniya Choli', 'Row B · Stall 2', '10% off on showing this page', [['name' => 'Chaniya choli set', 'price' => 2500, 'note' => 'rental from 600']], 191],
            ['Pav Bhaji Junction', 'Food court', null, [['name' => 'Pav bhaji', 'price' => 90], ['name' => 'Cheese pav bhaji', 'price' => 120]], 356],
            ['Oxidised Jewellery', 'Row B · Stall 5', 'Buy 2 get 1 free', [['name' => 'Earrings', 'price' => 150], ['name' => 'Necklace set', 'price' => 450]], 143],
            ['Dandiya Sticks', 'Near Gate 1', null, [['name' => 'Plain pair', 'price' => 80], ['name' => 'Decorated pair', 'price' => 200]], 97],
        ])->map(fn ($s) => tap($event->stalls()->create(['name' => $s[0], 'location' => $s[1], 'offers' => $s[2], 'products' => $s[3]]), fn ($st) => $st->forceFill(['view_count' => $s[4]])->save()));

        // ---- Attendees + passes (registered over 14 days, walk-ups tonight) ------------
        $total = 1500;
        $arrivedCount = 1120;
        $attendeeRows = [];
        $regStart = $now->copy()->subDays(14);
        for ($i = 0; $i < $total; $i++) {
            $walkup = $i >= $total - 180;
            $created = $walkup
                ? $tonight->copy()->addMinutes(mt_rand(-30, 170))
                : $regStart->copy()->addMinutes(mt_rand(0, 14 * 24 * 60) * (mt_rand(0, 9) < 7 ? 1 : 0.5)); // heavier in the last week
            $attendeeRows[] = [
                'event_id' => $event->id,
                'name' => self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)],
                'phone' => '9'.mt_rand(6, 9).str_pad((string) (100000000 + $i * 7919 % 900000000), 8, '0', STR_PAD_LEFT),
                'email' => null,
                'ticket_type' => $i < 60 ? 'vip' : 'general',
                'is_vip' => $i < 60,
                'share_contact' => mt_rand(0, 9) < 3,
                'source' => $walkup ? 'walkup' : 'online',
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }
        foreach (array_chunk($attendeeRows, 500) as $chunk) {
            DB::table('attendees')->insert($chunk);
        }
        $attendeeIds = $event->attendees()->orderBy('id')->pluck('id')->all();

        $codes = [];
        $passRows = [];
        foreach ($attendeeIds as $aid) {
            do {
                $code = Pass::generateCode();
            } while (isset($codes[$code]));
            $codes[$code] = true;
            $passRows[] = ['event_id' => $event->id, 'attendee_id' => $aid, 'code' => $code, 'revoked' => false, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach (array_chunk($passRows, 500) as $chunk) {
            DB::table('passes')->insert($chunk);
        }
        $passIds = $event->passes()->orderBy('id')->pluck('id')->all();

        // ---- Volunteers, roster, duty logs -----------------------------------------------
        $volNames = ['Ravi Parmar', 'Priya Dave', 'Amit Solanki', 'Meera Pandya', 'Kiran Vaghela', 'Nirav Thakkar', 'Sneha Raval', 'Jay Chauhan'];
        $volunteers = collect($volNames)->map(function ($n) use ($event) {
            $u = User::create(['name' => $n, 'email' => Str::slug($n).'.'.$event->id.'@demo.gatezo.local', 'password' => Str::random(32)]);
            $event->members()->attach($u->id, ['role' => 'volunteer']);

            return $u;
        });
        $posts = [$gates[0], $gates[0], $gates[1], $gates[1], $gates[2], $zones[0], $zones[1], $zones[2]];
        foreach ($volunteers as $i => $v) {
            $shift = $event->shifts()->create([
                'volunteer_name' => $v->name, 'volunteer_id' => $i === 7 ? null : $v->id, 'gate_id' => $posts[$i]->id,
                'starts_at' => $tonight->copy()->subMinutes(45), 'ends_at' => $tonight->copy()->addHours(4), 'label' => $i < 2 ? 'Registration desk' : null,
            ]);
            if ($i === 7) {
                continue; // Jay never showed up → "not arrived"
            }
            $event->dutyLogs()->create(['volunteer_id' => $v->id, 'gate_id' => $i === 4 ? $gates[1]->id : $posts[$i]->id, 'status' => 'on', 'at' => $tonight->copy()->subMinutes(mt_rand(20, 50))]);
        }
        $scanners = [$gates[0]->id => [$volunteers[0]->id, $volunteers[1]->id], $gates[1]->id => [$volunteers[2]->id, $volunteers[3]->id], $gates[2]->id => [$volunteers[4]->id]];

        // ---- Arrivals on a garba curve: slow 19:30, peak ~21:00, tail to 23:00 ---------------
        $checkinRows = [];
        $arrived = array_slice($passIds, 0, $arrivedCount);
        shuffle($arrived);
        foreach ($arrived as $i => $pid) {
            // Beta-ish distribution over 0..210 minutes: sum of two uniforms, skewed late.
            $m = (int) ((mt_rand(0, 100) + mt_rand(0, 100) + mt_rand(0, 60)) / 260 * 210);
            $at = $tonight->copy()->addMinutes($m);
            if ($at->gt($now)) {
                continue;
            }
            $gate = $i < 40 ? $gates[2] : ($m > 60 && mt_rand(0, 9) < 3 ? $gates[1] : $gates[0]);
            $checkinRows[] = ['event_id' => $event->id, 'pass_id' => $pid, 'gate_id' => $gate->id, 'direction' => 'in', 'scanned_by' => $scanners[$gate->id][array_rand($scanners[$gate->id])], 'scanned_at' => $at, 'synced_at' => $at->copy()->addSeconds(mt_rand(1, 90)), 'client_id' => (string) Str::uuid(), 'duplicate_flag' => false];
            // ~8% step out and come back; ~3% leave for good; a few duplicates across gates.
            $r = mt_rand(0, 99);
            if ($r < 8) {
                $out = $at->copy()->addMinutes(mt_rand(20, 60));
                $back = $out->copy()->addMinutes(mt_rand(5, 25));
                if ($out->lt($now)) {
                    $checkinRows[] = ['event_id' => $event->id, 'pass_id' => $pid, 'gate_id' => $gate->id, 'direction' => 'out', 'scanned_by' => $scanners[$gate->id][0], 'scanned_at' => $out, 'synced_at' => $out, 'client_id' => (string) Str::uuid(), 'duplicate_flag' => false];
                }
                if ($back->lt($now)) {
                    $checkinRows[] = ['event_id' => $event->id, 'pass_id' => $pid, 'gate_id' => $gate->id, 'direction' => 'in', 'scanned_by' => $scanners[$gate->id][0], 'scanned_at' => $back, 'synced_at' => $back, 'client_id' => (string) Str::uuid(), 'duplicate_flag' => false];
                }
            } elseif ($r < 11) {
                $out = $at->copy()->addMinutes(mt_rand(60, 150));
                if ($out->lt($now)) {
                    $checkinRows[] = ['event_id' => $event->id, 'pass_id' => $pid, 'gate_id' => $gate->id, 'direction' => 'out', 'scanned_by' => $scanners[$gate->id][0], 'scanned_at' => $out, 'synced_at' => $out, 'client_id' => (string) Str::uuid(), 'duplicate_flag' => false];
                }
            } elseif ($r < 12) {
                $dup = $at->copy()->addMinutes(mt_rand(1, 6));
                $other = $gate->id === $gates[0]->id ? $gates[1] : $gates[0];
                $checkinRows[] = ['event_id' => $event->id, 'pass_id' => $pid, 'gate_id' => $other->id, 'direction' => 'in', 'scanned_by' => $scanners[$other->id][0], 'scanned_at' => $dup, 'synced_at' => $dup->copy()->addMinutes(3), 'client_id' => (string) Str::uuid(), 'duplicate_flag' => true];
            }
        }
        foreach (array_chunk($checkinRows, 500) as $chunk) {
            DB::table('checkins')->insert($chunk);
        }

        // ---- Leads: opted-in attendees who visited stalls ------------------------------------
        $optedIn = $event->attendees()->where('share_contact', true)->pluck('id')->shuffle();
        $leadRows = [];
        foreach ($stalls as $k => $stall) {
            foreach ($optedIn->slice($k * 25, [42, 18, 31, 12, 27, 9][$k]) as $aid) {
                $leadRows[] = ['stall_id' => $stall->id, 'attendee_id' => $aid, 'note' => null, 'created_at' => $tonight->copy()->addMinutes(mt_rand(30, 200)), 'updated_at' => $now];
            }
        }
        DB::table('leads')->insert($leadRows);

        // ---- Feedback: skewed positive, some comments --------------------------------------
        $fbRows = [];
        $dist = [5 => 46, 4 => 31, 3 => 14, 2 => 6, 1 => 3];
        $withNames = $event->attendees()->inRandomOrder()->limit(60)->pluck('id')->all();
        $n = 0;
        foreach ($dist as $rating => $count) {
            for ($i = 0; $i < $count; $i++) {
                $comment = mt_rand(0, 9) < 3 ? self::COMMENTS[$rating][array_rand(self::COMMENTS[$rating])] : null;
                $fbRows[] = ['event_id' => $event->id, 'attendee_id' => $n < 60 ? $withNames[$n] : null, 'rating' => $rating, 'categories' => null, 'comment' => $comment, 'created_at' => $tonight->copy()->addMinutes(mt_rand(120, 230)), 'updated_at' => $now];
                $n++;
            }
        }
        DB::table('feedback')->insert($fbRows);

        // ---- Lucky draws: one finished with proof, one ready for the demo -------------------
        $done = $event->draws()->create(['name' => 'Day 5 lucky draw', 'pool_source' => 'checked_in', 'alternates_per_prize' => 1, 'claim_minutes' => 5]);
        $done->prizes()->create(['name' => 'Gift voucher ₹500', 'quantity' => 3, 'sort_order' => 0]);
        $done->prizes()->create(['name' => 'Mixer grinder', 'quantity' => 1, 'sort_order' => 1]);
        DrawEngine::run($done, $organizer);
        $forfeitOnce = true;
        while ($w = DrawEngine::announceNext($done->fresh())) {
            if ($forfeitOnce && $w->slot === 2) {
                DrawEngine::forfeit($w);
                $forfeitOnce = false;

                continue;
            }
            DrawEngine::claim($w, $volunteers[6]);
        }
        DB::table('draws')->where('id', $done->id)->update(['run_at' => $tonight->copy()->addMinutes(150)]);

        $next = $event->draws()->create(['name' => 'Grand finale draw', 'pool_source' => 'inside_now', 'alternates_per_prize' => 2, 'claim_minutes' => 5]);
        $next->prizes()->create(['name' => 'Bluetooth speaker', 'quantity' => 2, 'sort_order' => 0]);
        $next->prizes()->create(['name' => 'Smart TV 32"', 'quantity' => 1, 'sort_order' => 1]);
        $next->prizes()->create(['name' => 'Activa scooter', 'quantity' => 1, 'sort_order' => 2]);

        $this->command?->info("Demo event ready: /admin/{$event->slug} · demo@gatezo.local / password · volunteer code 246810");
    }
}
