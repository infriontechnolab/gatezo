<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Strict passes: the pass QR rotates every 30 s, so a screenshot dies within a minute.
            // Costs the attendee a live connection at the gate; off by default.
            $table->boolean('strict_passes')->default(false)->after('allow_reentry');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('strict_passes'));
    }
};
