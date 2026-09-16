<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A user's role within one event. Same user can organize event A and volunteer at event B.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_members', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['organizer', 'volunteer', 'vendor']);
            $table->timestamps();

            $table->primary(['event_id', 'user_id', 'role']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_members');
    }
};
