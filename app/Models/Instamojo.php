<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instamojo extends Model
{
    use HasFactory;
    protected $table = 'instamojos';
    protected $fillable = [
        'user_id',
        'instamojo_api_key',
        'instamojo_auth_token',
        'status'
    ];
}
