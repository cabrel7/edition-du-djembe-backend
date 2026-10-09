<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
{
    public function up()
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description');
            $table->string('extension')->nullable();
      
            $table->string('image')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->string('price_range')->nullable();
           
            $table->timestamps();

            // $table->index(['is_active', 'order']);
            // $table->index('slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('services');
    }
}