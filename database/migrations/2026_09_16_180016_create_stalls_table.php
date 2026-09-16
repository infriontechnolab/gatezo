<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stalls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Optional vendor login. v1 vendors use a signed private link instead.
            $table->foreignId('vendor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('public_code', 16)->unique(); // in the attendee-facing stall QR
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('location', 80)->nullable(); // "Row C, Stall 12"
            $table->json('products')->nullable();       // [{name, price, note}]
            $table->text('offers')->nullable();
            $table->unsignedInteger('view_count')->default(0); // attendee scans of the public QR
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stalls');
    }
};
