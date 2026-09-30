<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Plans now differ only in how many events you can run; every event is any size, with any team. */
return new class extends Migration
{
    private const DESCRIPTIONS = [
        // slug => [old seeded text, new text]; a description Ops already edited is left alone.
        'free' => ['Everything, for one small event.', 'Everything, for your first event. Any size.'],
        'starter' => ['For a society fair or a mid-size expo.', 'For committees that run a few events a month.'],
        'pro' => ['No caps. For big events and organizers who run several.', 'Unlimited events, for organizers who run many.'],
    ];

    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['max_attendees', 'max_team']);
        });

        foreach (self::DESCRIPTIONS as $slug => [$old, $new]) {
            DB::table('subscription_plans')->where('slug', $slug)->where('description', $old)->update(['description' => $new]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('max_attendees')->nullable()->after('max_events');
            $table->unsignedInteger('max_team')->nullable()->after('max_attendees');
        });

        foreach (self::DESCRIPTIONS as $slug => [$old, $new]) {
            DB::table('subscription_plans')->where('slug', $slug)->where('description', $new)->update(['description' => $old]);
        }
        DB::table('subscription_plans')->where('slug', 'free')->update(['max_attendees' => 200, 'max_team' => 1]);
        DB::table('subscription_plans')->where('slug', 'starter')->update(['max_attendees' => 1000, 'max_team' => 3]);
    }
};
