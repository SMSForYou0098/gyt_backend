<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashfreeConfig extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'app_id','status',
        'secret_key',
        'env'
    ];

}
