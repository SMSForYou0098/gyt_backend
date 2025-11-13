<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Razorpay extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'razorpay_key',
        'razorpay_secret',
        'status',
    ];
}
