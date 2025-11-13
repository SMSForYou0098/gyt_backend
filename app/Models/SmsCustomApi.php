<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsCustomApi extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'url',
        // Add other fields as needed
    ];
}
