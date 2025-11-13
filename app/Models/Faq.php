<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use HasFactory,SoftDeletes;
    protected $casts = [
        'links' => 'array',
        'is_active' => 'boolean',
    ];
     public function categoryData()
    {
        return $this->belongsTo(Query::class, 'category', 'id')
                    ->select('id', 'title');    
    }
}
