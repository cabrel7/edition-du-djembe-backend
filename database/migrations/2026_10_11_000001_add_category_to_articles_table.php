<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La colonne articles.category existait dans le dump de prod mais jamais dans
 * les migrations — toute install fraîche cassait le boot public (BOOT_COLUMNS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedBigInteger('category')->nullable()->after('content');
            $table->foreign('category')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['category']);
            $table->dropColumn('category');
        });
    }
};
