<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsConfig extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'url',
        'user_id',
        'api_key',
        'sender_id',
        'status'
    ];
}
