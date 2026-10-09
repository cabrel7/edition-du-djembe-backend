<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideosTable extends Migration
{
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique()->nullable();
            $table->text('description');
            $table->string('thumbnail');
            $table->string('video_url');
          
             $table->integer('status')->default(0)->comment('0: draft, 1: published, 2: private');
            
          
            $table->timestamps();

            // $table->index(['category', 'status']);
            // $table->index('published_date');
            // $table->index('is_featured');
        });
    }

    public function down()
    {
        Schema::dropIfExists('videos');
    }
}