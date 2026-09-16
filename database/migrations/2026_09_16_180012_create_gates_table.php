<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Entry gates (attendee check-in) and duty zones (volunteer "I'm here" posts).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);           // "Main Gate", "Hall B"
            $table->string('code', 12);           // short human-readable fallback printed under the QR, e.g. G2
            $table->boolean('is_entry')->default(true); // false = duty zone only
            $table->timestamps();

            $table->unique(['event_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gates');
    }
};
