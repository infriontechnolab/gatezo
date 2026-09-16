<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Multi-gate, offline-syncable, re-entry aware.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['in', 'out'])->default('in');
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            // When the scan happened on the device. Source of truth for ordering;
            // may predate synced_at if the scan was queued offline.
            $table->dateTime('scanned_at', 3);
            $table->dateTime('synced_at', 3)->useCurrent();
            // Device-generated UUID → idempotent replay on sync.
            $table->uuid('client_id')->unique();
            // Same pass + direction already recorded at another gate inside the
            // dedupe window. Surfaced to the organizer, never blocks the gate.
            $table->boolean('duplicate_flag')->default(false);

            $table->index('pass_id');
            $table->index(['event_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
