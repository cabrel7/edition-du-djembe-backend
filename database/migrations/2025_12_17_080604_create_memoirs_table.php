<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMemoirsTable extends Migration
{
    public function up()
    {
        Schema::create('memoirs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('university');
            $table->unsignedBigInteger('domain');
            $table->year('year');
            $table->text('abstract');
             $table->string('isbn')->unique()->nullable();
            $table->string('cover')->nullable();
                 $table->string('slug')->unique();
            $table->string('document')->nullable();
             $table->string('extension')->nullable();
            $table->json('keywords')->nullable();
            $table->string('supervisor')->nullable();
            $table->date('defense_date')->nullable();
            $table->string('grade')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('downloads_count')->default(0);
             $table->integer('status')->default(0)->comment('0: pending, 1: approved, 2: rejected');
            
            $table->timestamps();
              $table->foreign('domain')->references('id')->on('domaines')->onDelete('cascade');
            // $table->foreign('author')->references('id')->on('users')->onDelete('cascade');
            // $table->index(['university', 'domain']);
            // $table->index('year');
            // $table->index('author');
        });
    }

    public function down()
    {
        Schema::dropIfExists('memoirs');
    }
}