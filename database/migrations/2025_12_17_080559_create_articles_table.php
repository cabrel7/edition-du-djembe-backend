<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticlesTable extends Migration
{
    public function up()
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt');
            $table->longText('content');
            $table->string('document')->nullable();
            $table->string('image')->nullable();
            $table->dateTime('published_date')->nullable();
            $table->string('author')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('tags')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('reading_time')->nullable();
            $table->integer('status')->default(0)->comment('0: draft, 1: published, 2: archived');
            $table->boolean('featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
         
        });
    }

    public function down()
    {
        Schema::dropIfExists('articles');
    }
}
