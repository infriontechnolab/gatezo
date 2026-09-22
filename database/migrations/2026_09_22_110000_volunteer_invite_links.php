<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
| Personal invite links for volunteers: each roster entry (shift) carries a random token
| that opens the scanner as that person, bound to the first phone that uses it. Lets an
| organizer turn the shared 6-digit code off entirely (events.join_by_code).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->string('invite_token', 40)->nullable()->unique()->after('label');
            $table->dateTime('invite_used_at')->nullable()->after('invite_token');
            $table->string('invite_device', 16)->nullable()->after('invite_used_at'); // sha1 prefix of the device cookie
        });
        // Existing roster entries get a link too.
        foreach (DB::table('shifts')->whereNull('invite_token')->pluck('id') as $id) {
            DB::table('shifts')->where('id', $id)->update(['invite_token' => Str::random(40)]);
        }
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('join_by_code')->default(true)->after('require_volunteer_approval');
        });
        // volunteer_joins.result gains the link outcomes; code becomes optional (links carry none).
        DB::statement('ALTER TABLE volunteer_joins MODIFY code VARCHAR(6) NULL');
        DB::statement("ALTER TABLE volunteer_joins MODIFY result ENUM('ok','wrong_code','not_on_roster','kicked','locked_out','code_off','invite','invite_used','invite_expired') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('shifts', fn (Blueprint $table) => $table->dropColumn(['invite_token', 'invite_used_at', 'invite_device']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('join_by_code'));
        DB::statement("ALTER TABLE volunteer_joins MODIFY result ENUM('ok','wrong_code','not_on_roster','kicked','locked_out') NOT NULL");
        DB::statement('ALTER TABLE volunteer_joins MODIFY code VARCHAR(6) NOT NULL');
    }
};
