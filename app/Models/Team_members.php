<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team_members extends Model 
{
    use HasFactory;

    protected $table = 'team_members'; // adapte au nom réel de la table

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'role',
        'photo',
        'bio',
        'social_links',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];
}
