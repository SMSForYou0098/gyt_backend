<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoginHistory extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'user_id',
        'ip_address',
        'location',
        'country',
        'state',
        'city',
        'login_time',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
