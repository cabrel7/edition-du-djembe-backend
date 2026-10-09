<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['book', 'memoir', 'article'])->default('book');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('summary');
            $table->string('file_path');
            $table->string('file_name');

            $table->enum('status', ['pending', 'reviewing', 'accepted', 'rejected', 'archived'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->boolean('rgpd_consent')->default(false);
            $table->timestamps();
            
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
