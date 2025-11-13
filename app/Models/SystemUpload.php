<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemUpload extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'system_uploads';
    protected $guarded = ['id'];
}
