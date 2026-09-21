<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Print kit look: classic (black on white, any printer), bold (accent bands), festival (full colour).
            $table->string('kit_style', 12)->default('bold')->after('logo_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('kit_style'));
    }
};
