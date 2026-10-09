<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'author',
        'category',
        'cover',
        'extraitdocument',
        'description',
        'published_date',
        'price',
        'slug',
        'isbn',
        'pages',
        'stock_quantity',
        'language',
        'publisher',
        'edition',
        'rating',
        'status',
        'document'
    ];

    protected $casts = [
        'published_date' => 'date',
        'price' => 'decimal:2',
        'rating' => 'decimal:2',
        'pages' => 'integer',
        'stock_quantity' => 'integer'
    ];

    // Relation avec les commandes
    // public function orders()
    // {
    //     return $this->belongsToMany(Order::class)
    //         ->withPivot('quantity', 'unit_price')
    //         ->withTimestamps();
    // }

    // Relation avec les catégories
    public function categories()
    {
        return $this->belongsToMany(categorie::class);
    }

    // Scope pour les livres disponibles
    public function scopeAvailable($query)
    {
        return $query->where('stock_quantity', '>', 0)
                     ->where('status', 'active');
    }
}