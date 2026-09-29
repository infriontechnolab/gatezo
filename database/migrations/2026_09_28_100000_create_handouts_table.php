<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Goodies: one kit per attendee, handed out at a goodies counter by scanning the pass.
// Kept apart from checkins so "inside now" and arrival counts never see a handout.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('goodies_enabled')->default(false)->after('join_by_code');
            $table->string('goodies_name', 60)->nullable()->after('goodies_enabled');       // "Welcome kit", "T-shirt"
            $table->unsignedInteger('goodies_stock')->nullable()->after('goodies_name');    // null = not counting
            $table->boolean('goodies_after_checkin')->default(true)->after('goodies_stock');
            $table->json('goodies_ticket_types')->nullable()->after('goodies_after_checkin'); // null/[] = every ticket type
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->boolean('is_goodies')->default(false)->after('is_entry');
        });

        Schema::create('handouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scanned_at', 3);
            $table->dateTime('synced_at', 3)->useCurrent();
            $table->uuid('client_id')->unique();
            // Why the server thinks this pass should not have got one:
            // already_collected, not_checked_in, ticket_type. Null = a clean handout.
            $table->string('flag', 20)->nullable();
            // What the volunteer chose when the phone warned: gave_anyway or refused.
            // Refused rows are a record of the attempt, not a handout.
            $table->string('decision', 12)->nullable();

            $table->index('pass_id');
            $table->index(['event_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handouts');
        Schema::table('gates', fn (Blueprint $table) => $table->dropColumn('is_goodies'));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn([
            'goodies_enabled', 'goodies_name', 'goodies_stock', 'goodies_after_checkin', 'goodies_ticket_types',
        ]));
    }
};
