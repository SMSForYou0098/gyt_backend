<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatLayout extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable =  [
        'category_id',
        'qr_code',
        'user_photo',
        'text_1',
        'text_2',
        'text_3'
    ];
    protected $casts = [
        'userPhoto' => 'array',
        'text_1' => 'array',
        'text_2' => 'array',
        'text_3' => 'array',
        'qrCode' => 'array',
    ];
}
