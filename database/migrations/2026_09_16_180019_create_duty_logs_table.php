<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Volunteer scans a gate/zone QR → "on duty here" with a timestamp.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['on', 'off'])->default('on');
            $table->dateTime('at');

            $table->index(['event_id', 'at']);
            $table->index('volunteer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_logs');
    }
};
