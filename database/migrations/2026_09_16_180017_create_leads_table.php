<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Vendor scans an attendee's pass, attendee opts in → lead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stall_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['stall_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
