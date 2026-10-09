<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Memoir extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'university',
        'domain',
        'year',
        'slug',
        'abstract',
        'download_url',
        'file_size',
        'file_format',
        'keywords',
        'supervisor',
        'defense_date',
        'grade',
        'views_count',
        'downloads_count',
        'status',
        'cover',
        'document'
    ];

    protected $casts = [
        'year' => 'integer',
        'defense_date' => 'date',
        'views_count' => 'integer',
        'downloads_count' => 'integer',
        'file_size' => 'integer'
    ];

    // Relation avec l'auteur (utilisateur)
    // public function authorUser()
    // {
    //     return $this->belongsTo(User::class, 'author_id');
    // }

    // // Relation avec l'université
    // public function universityRelation()
    // {
    //     return $this->belongsTo(University::class, 'university_id');
    // }

    // Scope pour les mémoires récents
    public function scopeRecent($query, $years = 5)
    {
        $currentYear = date('Y');
        return $query->where('year', '>=', $currentYear - $years);
    }
}