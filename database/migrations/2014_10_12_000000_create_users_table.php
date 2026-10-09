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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('role')->nullable();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->integer('verified')->nullable();
          
            $table->integer('phone')->nullable();
       
            $table->integer('is_premium')->nullable();
            $table->string('country')->nullable();
         
            $table->string('prenom');
          
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->text('password');
            $table->rememberToken();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
