<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Baked into the vendor link signature; bump it to invalidate a forwarded link.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stalls', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_version')->default(1)->after('public_code');
        });
    }

    public function down(): void
    {
        Schema::table('stalls', function (Blueprint $table) {
            $table->dropColumn('link_version');
        });
    }
};
