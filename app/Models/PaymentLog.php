<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentLog extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'session_id',
        'easepayid',
        'amount',
      	'payment_id',
        'status',
        'txnid',
        'mode',
        'addedon',
        'params', // Allow storing the entire params data
    ];

    protected $casts = [
        'params' => 'json', // Cast params as JSON
    ];
    
}
