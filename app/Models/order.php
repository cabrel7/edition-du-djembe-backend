<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'order_date',
        'status',
        'total_amount',
        'shipping_address',
        'billing_address',
        'payment_method',
        'transaction_id'
    ];
}
