<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
       
        'slug',
    
        'image',
      
        'is_active',
        'price_range',
       
    ];

    protected $casts = [
        // 'features' => 'ar/ray',
        'order' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relation avec les demandes de service
    public function serviceRequests()
    {
        return $this->hasMany(Service_request::class);
    }

    // Relation avec les témoignages
    public function testimonials()
    {
        return $this->hasMany(Comment::class);
    }

    // Scope pour les services actifs
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->orderBy('order');
    }
}