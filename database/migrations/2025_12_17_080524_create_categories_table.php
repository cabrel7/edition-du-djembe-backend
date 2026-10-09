<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->nullable();
            $table->string('type')->nullable(); // book, article, video, etc.
            $table->text('description')->nullable();
          
            // $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            // $table->foreignId('parent_id')->nullable()->constrained('categories');
            $table->timestamps();

            // $table->index(['type', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('categories');
    }
}