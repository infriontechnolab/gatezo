<?php

use App\Enums\AttendeeSource;
use App\Enums\TicketType;
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
            $table->string('ticket_type', 40)->default(TicketType::General->value);
            $table->boolean('is_vip')->default(false);
            // WhatsApp news about the organizer's next events. Null = the form didn't ask.
            $table->boolean('marketing_opt_in')->nullable();
            $table->timestamp('marketing_opt_in_at')->nullable(); // when they last answered: the consent record
            $table->enum('source', ['online', 'walkup', 'import'])->default(AttendeeSource::Online->value);
            $table->json('extra')->nullable(); // custom per-event form fields
            $table->string('external_id', 100)->nullable(); // their ID in the system they were imported from
            $table->timestamps();

            $table->index(['event_id', 'phone']);
            $table->index(['event_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendees');
    }
};
