<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('memoirs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
