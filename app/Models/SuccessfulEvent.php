<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuccessfulEvent extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'successful_events';
    protected $guarded = ['id'];

}
