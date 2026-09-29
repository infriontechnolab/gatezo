<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Plans the organizer can choose, and the dated periods they pay for. Payment is taken by
// hand (we call back, UPI / bank transfer); Ops confirms it and records the period. An
// organizer is on a paid plan only while today falls inside one of their periods.
// users.plan stays as the base plan (free, or a comped plan with no end date).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique(); // what users.plan and subscriptions.plan hold; fixed once made
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            // Caps. null = unlimited.
            $table->unsignedInteger('max_events')->nullable();
            $table->unsignedInteger('max_attendees')->nullable();
            $table->unsignedInteger('max_team')->nullable();
            // Rupees. null = not sold on that billing cycle; both null = not for sale (Free).
            $table->unsignedInteger('price_monthly')->nullable();
            $table->unsignedInteger('price_yearly')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort')->default(0); // also the rank: higher wins when periods overlap
            $table->timestamps();
        });

        // Starting catalogue at launch prices; change them any time in Ops → Plans.
        $now = now();
        DB::table('subscription_plans')->insert([
            ['slug' => 'free', 'name' => 'Free', 'description' => 'Everything, for one small event.', 'max_events' => 1, 'max_attendees' => 200, 'max_team' => 1, 'price_monthly' => null, 'price_yearly' => null, 'is_featured' => false, 'sort' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'starter', 'name' => 'Starter', 'description' => 'For a society fair or a mid-size expo.', 'max_events' => 3, 'max_attendees' => 1000, 'max_team' => 3, 'price_monthly' => 199, 'price_yearly' => 1499, 'is_featured' => false, 'sort' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pro', 'name' => 'Pro', 'description' => 'No caps. For big events and organizers who run several.', 'max_events' => null, 'max_attendees' => null, 'max_team' => null, 'price_monthly' => 499, 'price_yearly' => 3999, 'is_featured' => true, 'sort' => 20, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 32);
            $table->string('billing', 8)->nullable(); // monthly | yearly | null = custom dates
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedInteger('amount')->nullable(); // rupees received
            $table->string('payment_ref', 120)->nullable(); // UPI ref, UTR, invoice no.
            $table->string('note', 500)->nullable();
            $table->foreignId('upgrade_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'ends_on']);
        });

        // The request now names the plan the organizer chose, and the number we call back on.
        Schema::table('upgrade_requests', function (Blueprint $table) {
            $table->string('plan', 32)->nullable()->after('event_id');
            $table->string('billing', 8)->nullable()->after('plan');
            $table->string('phone', 20)->nullable()->after('billing');
        });
    }

    public function down(): void
    {
        Schema::table('upgrade_requests', function (Blueprint $table) {
            $table->dropColumn(['plan', 'billing', 'phone']);
        });
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
