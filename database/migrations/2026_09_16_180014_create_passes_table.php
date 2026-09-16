<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One pass per attendee. QR payload = code + HMAC(code, event.pass_secret) so
// a scanner with the cached event secret can verify authenticity offline.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code', 16)->unique();
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
