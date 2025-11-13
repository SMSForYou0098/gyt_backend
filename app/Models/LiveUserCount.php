<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiveUserCount extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'live_user_counts';
    protected $guarded = ['id'];

}
