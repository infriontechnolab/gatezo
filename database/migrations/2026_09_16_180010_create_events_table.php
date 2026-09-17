<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique(); // public URL handle
            $table->string('name');
            $table->enum('type', ['community', 'sports', 'festival', 'workshop', 'religious', 'college', 'other'])->default('other');
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            // Per-event theme
            $table->string('accent_hex', 7)->default('#E8604C');
            $table->string('logo_url')->nullable();
            // Capacity for the live gauge; null = uncapped. Advisory only, never enforced at the gate.
            $table->unsignedInteger('capacity')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('allow_self_register')->default(true); // walk-up QR registration
            $table->boolean('allow_reentry')->default(false);      // scan out / scan in
            // 6-digit code volunteers type to get a scanner session (no account needed)
            $table->string('volunteer_code', 6);
            // Per-event HMAC secret for pass tokens. Rotate to revoke every pass at once.
            $table->string('pass_secret', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
