<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockedIpAddress extends Model
{
    use HasFactory,SoftDeletes;

     protected $fillable = [
        'user_id',
        'ip_address',
        'event_id',
        'session_id',
        'user_agent',
        'url',
        'domain',
    ];

}
