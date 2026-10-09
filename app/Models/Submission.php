<?php
// app/Models/Submission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'email',
        'phone',
        'title',
        'category',
        'summary',
        'file_path',
        'file_name',
      
        'status',
        'admin_notes',
        'rgpd_consent'
    ];

    protected $casts = [
        'rgpd_consent' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = ['author', 'formatted_file_size', 'file_url'];

    /**
     * Scopes pour filtrer par type
     */
    public function scopeBooks($query)
    {
        return $query->where('type', 'book');
    }

    public function scopeMemoirs($query)
    {
        return $query->where('type', 'memoir');
    }

    public function scopeArticles($query)
    {
        return $query->where('type', 'article');
    }

    /**
     * Scopes pour filtrer par statut
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewing($query)
    {
        return $query->where('status', 'reviewing');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Accesseur : Nom complet de l'auteur
     */
    public function getAuthorAttribute()
    {
        return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
    }

    /**
     * Accesseur : Taille formatée du fichier
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Accesseur : URL publique du fichier
     */
    public function getFileUrlAttribute()
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Accesseur : Type de document formaté
     */
    public function getTypeLabelAttribute()
    {
        $labels = [
            'book' => 'Livre',
            'memoir' => 'Mémoire',
            'article' => 'Article'
        ];
        
        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Accesseur : Statut formaté
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'pending' => 'En attente',
            'reviewing' => 'En révision',
            'accepted' => 'Accepté',
            'rejected' => 'Rejeté',
            'archived' => 'Archivé'
        ];
        
        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Vérifie si l'auteur a déjà soumis d'autres documents
     */
    public function authorHasOtherSubmissions()
    {
        return self::where('email', $this->email)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    /**
     * Compte les autres soumissions de l'auteur
     */
    public function authorSubmissionCount()
    {
        return self::where('email', $this->email)
            ->where('id', '!=', $this->id)
            ->count();
    }
}