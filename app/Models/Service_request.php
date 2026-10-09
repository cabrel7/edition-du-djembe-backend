<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service_request extends Model
{
    use HasFactory;
    protected $fillable = [
        'service_id',
        'user_id',
        'request_date',
        'status',
        'details',
        'response_date'
    ];
}
