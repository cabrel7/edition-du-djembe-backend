<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign('books_category_foreign');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreign('category')->references('id')->on('categories')->onDelete('restrict');
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->dropForeign('memoirs_domain_foreign');
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->foreign('domain')->references('id')->on('domaines')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropForeign('books_category_foreign');
        });

        Schema::table('books', function (Blueprint $table) {
            $table->foreign('category')->references('id')->on('categories')->onDelete('cascade');
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->dropForeign('memoirs_domain_foreign');
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->foreign('domain')->references('id')->on('domaines')->onDelete('cascade');
        });
    }
};
