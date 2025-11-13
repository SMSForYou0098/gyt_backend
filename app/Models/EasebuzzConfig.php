<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EasebuzzConfig  extends Model
{
    use HasFactory;
    protected $table = 'easebuzzs';
    protected $fillable = [
        'user_id',
        'merchant_key',
        'salt',
        'env',
        'prod_url',
        'test_url',
        'status'
    ];
}
