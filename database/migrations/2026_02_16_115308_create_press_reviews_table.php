<?php
// database/migrations/XXXX_XX_XX_XXXXXX_create_press_reviews_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('press_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle')->nullable();
            $table->text('summary');
            $table->longText('content');
            $table->string('author')->nullable();
            $table->string('source')->nullable(); // Nom du journal, magazine, site
            $table->string('source_url')->nullable(); // Lien vers l'article original
            $table->string('publication_date')->nullable(); // Date de publication originale
            $table->string('cover_image')->nullable();
            $table->json('gallery_images')->nullable(); // Plusieurs images
            $table->json('documents')->nullable(); // PDFs ou autres documents joints
            $table->string('video_url')->nullable(); // Lien vidéo associée
            $table->string('audio_url')->nullable(); // Lien audio associé
            $table->enum('category', ['interview', 'critique', 'reportage', 'article', 'autre'])->default('article');
            $table->json('tags')->nullable();
            $table->integer('views')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->date('published_at')->nullable();
            $table->json('meta_data')->nullable(); // SEO, métadonnées supplémentaires
            $table->timestamps();
            
            // Index
            $table->index('slug');
            $table->index('category');
            $table->index('status');
            $table->index('published_at');
            $table->index('is_featured');
        });
    }

    public function down()
    {
        Schema::dropIfExists('press_reviews');
    }
};
