<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lucky draw. A draw commits a seed hash when created, snapshots the eligible pool
// when run, and derives every winner + alternate from seed + snapshot, so the
// result is reproducible by anyone. See docs/DRAW-RD.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->enum('pool_source', ['inside_now', 'checked_in', 'registered'])->default('inside_now');
            $table->json('filters')->nullable();              // {ticket_types:[], feedback_given:bool, opted_in:bool}
            $table->boolean('exclude_previous_winners')->default(true);
            $table->unsignedSmallInteger('claim_minutes')->default(5);
            $table->unsignedTinyInteger('alternates_per_prize')->default(1);
            $table->json('presentation')->nullable();         // {style, reveal_seconds, confetti, show_phone_masked}
            $table->boolean('publish_results')->default(true);
            // Fairness: hash committed at create, seed revealed at run, pool frozen at run.
            $table->string('seed_hash', 64);
            $table->string('seed', 64)->nullable();
            $table->json('pool_snapshot')->nullable();        // [{code, name, phone_masked}] in pool order
            $table->enum('status', ['draft', 'ready', 'finished'])->default('draft');
            $table->dateTime('run_at')->nullable();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0); // drawn in this order; put the grand prize last
            $table->string('image_url')->nullable();
            $table->timestamps();
        });

        Schema::create('draw_winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draw_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prize_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pass_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot');   // 1..quantity within the prize
            $table->unsignedTinyInteger('rank');    // 1 = winner, 2.. = alternates in order
            $table->enum('status', ['pending', 'announced', 'claimed', 'forfeited'])->default('pending');
            $table->dateTime('announced_at')->nullable();
            $table->dateTime('claim_deadline')->nullable();
            $table->dateTime('claimed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['draw_id', 'pass_id']);           // one prize per person per draw
            $table->index(['draw_id', 'prize_id', 'slot', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draw_winners');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('draws');
    }
};
