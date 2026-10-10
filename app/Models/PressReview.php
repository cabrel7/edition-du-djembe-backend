<?php
// app/Models/PressReview.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PressReview extends Model
{
    use HasFactory;

    protected $table = 'press_reviews';

    protected $fillable = [
        'title',
        'slug',
        'subtitle',
        'summary',
        'content',
        'author',
        'source',
        'source_url',
        'publication_date',
        'cover_image',
        'gallery_images',
        'documents',
        'video_url',
        'audio_url',
        'category',
        'tags',
        'views',
        'is_featured',
        'status',
        'published_at',
        'meta_data'
    ];

    protected $casts = [
        'gallery_images' => 'array',
        'documents' => 'array',
        'tags' => 'array',
        'meta_data' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'date',
        'views' => 'integer'
    ];

    protected $appends = [
        'excerpt',
        'cover_url',
        'gallery_urls',
        'reading_time',
        'category_label'
    ];

    /**
     * Générer automatiquement le slug à partir du titre
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pressReview) {
            if (empty($pressReview->slug)) {
                $pressReview->slug = Str::slug($pressReview->title);
            }
            
            // Vérifier l'unicité du slug
            $count = static::where('slug', $pressReview->slug)->count();
            if ($count > 0) {
                $pressReview->slug = $pressReview->slug . '-' . ($count + 1);
            }
        });

        static::updating(function ($pressReview) {
            if ($pressReview->isDirty('title') && empty($pressReview->slug)) {
                $pressReview->slug = Str::slug($pressReview->title);
            }
        });
    }

    /**
     * Accesseurs
     */
    public function getExcerptAttribute()
    {
        return Str::limit($this->summary ?? $this->content, 200);
    }

    public function getCoverUrlAttribute()
    {
        if ($this->cover_image) {
            return asset($this->cover_image);
        }
        return asset('images/default-press-review.jpg');
    }

    public function getGalleryUrlsAttribute()
    {
        if (!$this->gallery_images) {
            return [];
        }

        return array_map(function ($image) {
            return asset($image);
        }, $this->gallery_images);
    }

    public function getReadingTimeAttribute()
    {
        $words = str_word_count(strip_tags($this->content));
        $minutes = ceil($words / 200); // 200 mots par minute
        return $minutes . ' min de lecture';
    }

    public function getCategoryLabelAttribute()
    {
        $labels = [
            'interview' => 'Interview',
            'critique' => 'Critique',
            'reportage' => 'Reportage',
            'article' => 'Article',
            'autre' => 'Autre'
        ];
        
        return $labels[$this->category] ?? $this->category;
    }

    public function getFormattedPublicationDateAttribute()
    {
        if ($this->publication_date) {
            return date('d/m/Y', strtotime($this->publication_date));
        }
        return null;
    }

    public function getFormattedPublishedAtAttribute()
    {
        if ($this->published_at) {
            return $this->published_at->format('d/m/Y');
        }
        return null;
    }

    public function getTagsListAttribute()
    {
        if (!$this->tags) {
            return [];
        }
        return $this->tags;
    }

    /**
     * Incrémenter le nombre de vues
     */
    public function incrementViews()
    {
        $this->increment('views');
    }

    /**
     * Vérifier si la revue de presse est publiée
     */
    public function isPublished()
    {
        return $this->status === 'published' && $this->published_at <= now();
    }

    /**
     * Publier la revue de presse
     */
    public function publish()
    {
        $this->status = 'published';
        $this->published_at = now();
        $this->save();
    }

    /**
     * Archiver la revue de presse
     */
    public function archive()
    {
        $this->status = 'archived';
        $this->save();
    }

    /**
     * Passer en brouillon
     */
    public function draft()
    {
        $this->status = 'draft';
        $this->save();
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('subtitle', 'LIKE', "%{$term}%")
              ->orWhere('summary', 'LIKE', "%{$term}%")
              ->orWhere('content', 'LIKE', "%{$term}%")
              ->orWhere('author', 'LIKE', "%{$term}%")
              ->orWhere('source', 'LIKE', "%{$term}%");
        });
    }

    public function scopeWithTag($query, $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
