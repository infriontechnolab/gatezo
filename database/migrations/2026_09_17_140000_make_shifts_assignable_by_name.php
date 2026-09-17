<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shifts are planned before volunteers exist (they're created when they join with the
// event code). So a shift is assigned by name; volunteer_id links up on join.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropForeign(['volunteer_id']);
            $table->foreignId('volunteer_id')->nullable()->change();
            $table->foreign('volunteer_id')->references('id')->on('users')->nullOnDelete();
            $table->string('volunteer_name', 60)->after('volunteer_id');
            $table->index(['event_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'starts_at']);
            $table->dropColumn('volunteer_name');
            $table->dropForeign(['volunteer_id']);
            $table->foreignId('volunteer_id')->nullable(false)->change();
            $table->foreign('volunteer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
