<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per phone number per organizer, across every event they created. Derived from
// attendees by App\Services\VisitorBook; never edited directly.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // the organizer who owns the list
            $table->string('phone', 20);
            $table->string('name', 120);
            $table->string('email')->nullable();
            $table->unsignedInteger('events_count')->default(1);
            $table->foreignId('last_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('marketing_opt_in_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
