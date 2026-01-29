<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeletedMaster extends Model
{
    use HasFactory,SoftDeletes;

      protected $fillable = [
        'original_table',
        'original_id',
        'data',
        'deleted_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
