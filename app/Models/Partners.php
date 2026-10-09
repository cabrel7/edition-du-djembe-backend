<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Partners extends Model 
{
    use HasFactory;

    protected $table = 'partners'; // adapte si le nom de la table est différent

    protected $fillable = [
        'nom',
        'image',
        'website',
        'description',
        'type',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];
}
