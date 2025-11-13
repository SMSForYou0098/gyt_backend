<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paytm extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'merchant_id',
        'merchant_key',
        'merchant_website',
        'industry_type',
        'channel',
    ];
}
