<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'thumbnail',
        'video_url',
       
       
        'slug',
        
        'status',

       
    ];

    protected $casts = [
        'published_date' => 'datetime',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'is_featured' => 'boolean',
        'tags' => 'array',
        'duration' => 'integer' // Durée en secondes
    ];

    // Relation avec la catégorie
    // public function categoryRelation()
    // {
    //     return $this->belongsTo(VideoCategory::class, 'category_id');
    // }

    // Relation avec les commentaires
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // Format de la durée
    public function getFormattedDurationAttribute()
    {
        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
        }

        return sprintf("%02d:%02d", $minutes, $seconds);
    }

    // Scope pour les vidéos publiées
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_date', '<=', now());
    }
}