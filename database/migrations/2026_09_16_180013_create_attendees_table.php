<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Attendees are NOT users. Light records, phone as soft identity, no OTP.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('ticket_type', 40)->default('general'); // general / vip / guest
            $table->boolean('is_vip')->default(false);
            $table->enum('source', ['online', 'walkup', 'import'])->default('online');
            $table->json('extra')->nullable(); // custom per-event form fields
            $table->timestamps();

            $table->index(['event_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
