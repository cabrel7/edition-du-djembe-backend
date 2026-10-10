<?php
// app/Models/Contact.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Récupère toutes les réponses associées à ce contact
     */
    

    /**
     * Scope pour les contacts non lus
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour les contacts lus
     */
    public function scopeRead($query)
    {
        return $query->where('status', 'read');
    }

    /**
     * Scope pour les contacts répondu
     */
    public function scopeReplied($query)
    {
        return $query->where('status', 'replied');
    }

    /**
     * Scope pour les contacts archivés
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Marquer le contact comme lu
     */
    public function markAsRead()
    {
        $this->status = 'read';
        return $this->save();
    }

    /**
     * Marquer le contact comme répondu
     */
    public function markAsReplied()
    {
        $this->status = 'replied';
        return $this->save();
    }

    /**
     * Marquer le contact comme archivé
     */
    public function markAsArchived()
    {
        $this->status = 'archived';
        return $this->save();
    }
}