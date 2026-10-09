<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentsTable extends Migration
{
    public function up()
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->text('content');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('commentable'); // Pour articles, vidéos, etc.
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();

            $table->index(['commentable_type', 'commentable_id', 'is_approved']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('comments');
    }
}