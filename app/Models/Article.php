<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'excerpt',
        'content',
        'category',
        'image',
        'published_date',
        'author',
        'slug',
        'meta_title',
        'meta_description',
        'tags',
        'views_count',
        
        'status',
    
    ];

    protected $casts = [
        'published_date' => 'datetime',
        'views_count' => 'integer',
        'reading_time' => 'integer',
       
        'tags' => 'array'
    ];

    // Relation avec l'auteur (utilisateur)
    public function authorUser()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Relation avec les commentaires
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // Scope pour les articles publiés
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_date', '<=', now());
    }

    // Scope pour les articles en vedette
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }
}