<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBooksTable extends Migration
{
    public function up()
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->unsignedBigInteger('category');
            $table->string('cover')->nullable();
            $table->text('description');
            $table->string('slug')->unique();
            $table->date('published_date')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('isbn')->unique();
            $table->integer('pages');
            $table->string('document');
               $table->string('extraitdocument');
            $table->integer('stock_quantity')->nullable();
            $table->string('language')->default('fr');
            $table->string('publisher')->nullable();
            $table->string('edition')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->boolean('status')->default(1);
            $table->foreign('category')->references('id')->on('categories')->onDelete('cascade');
            // $table->foreign('author')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // $table->index(['category']);
            // $table->index('author');
            // $table->index('isbn');
        });
    }

    public function down()
    {
        Schema::dropIfExists('books');
    }
}