<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    use HasFactory;
    protected $fillable = [
        'phone',
        'adress',
        'about_us',
        'email',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'youtube',
        'footer_description',
        'footer_copyright',
        'logo',
        'favicon',
     
    ];
}
