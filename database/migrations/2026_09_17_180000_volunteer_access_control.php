<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Volunteer access control: rotate the join code, roster-only / approval modes,
// kick, and a log of every join attempt.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('volunteer_code_version')->default(1)->after('volunteer_code');
            $table->boolean('roster_only')->default(false)->after('allow_reentry');       // only names on the shift roster can join
            $table->boolean('require_volunteer_approval')->default(false)->after('roster_only');
        });
        Schema::table('event_members', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable()->after('role');
            $table->dateTime('kicked_at')->nullable()->after('approved_at');
        });
        Schema::create('volunteer_joins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 60)->nullable();
            $table->string('code', 6);
            $table->enum('result', ['ok', 'wrong_code', 'not_on_roster', 'kicked', 'locked_out']);
            $table->string('ip', 45)->nullable();
            $table->string('device', 16)->nullable();       // sha1 prefix of the device cookie
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at');

            $table->index(['event_id', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_joins');
        Schema::table('event_members', fn (Blueprint $t) => $t->dropColumn(['approved_at', 'kicked_at']));
        Schema::table('events', fn (Blueprint $t) => $t->dropColumn(['volunteer_code_version', 'roster_only', 'require_volunteer_approval']));
    }
};
