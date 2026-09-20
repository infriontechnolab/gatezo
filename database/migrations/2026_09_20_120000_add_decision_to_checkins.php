<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkins', function (Blueprint $table) {
            // What the volunteer did when the scanner said "already inside":
            // let_in (override), turned_away (no entry), or null for a normal scan.
            $table->string('decision', 12)->nullable()->after('duplicate_flag');
            $table->index(['event_id', 'decision']);
        });
        // Turned-away scans are recorded for the organizer but are not entries or exits.
        DB::statement("ALTER TABLE checkins MODIFY direction ENUM('in', 'out', 'denied') NOT NULL DEFAULT 'in'");
    }

    public function down(): void
    {
        DB::table('checkins')->where('direction', 'denied')->delete();
        DB::statement("ALTER TABLE checkins MODIFY direction ENUM('in', 'out') NOT NULL DEFAULT 'in'");
        Schema::table('checkins', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'decision']);
            $table->dropColumn('decision');
        });
    }
};
