<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappApi extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'title',
        'user_id',
        'variables',
        'url',
    ];

    protected $casts = [
        'variables' => 'array', // Automatically handle JSON encoding/decoding
    ];
}
