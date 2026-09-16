<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Null = anonymous (the default). MySQL allows repeated NULLs in a
            // unique index, so the anti-spam constraint only bites for named feedback.
            $table->foreignId('attendee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5 overall
            $table->json('categories')->nullable(); // {venue: 4, food: 5} optional
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'attendee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
