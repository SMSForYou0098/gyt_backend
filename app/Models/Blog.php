<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Blog extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'categories' => 'array',
        'tags'       => 'array',
        'view_count' => 'integer',
    ];

    public function getCategoryRelationAttribute()
    {
        $categoryIds = collect($this->categories ?? [])->flatten()->toArray();

        return Category::whereIn('id', $categoryIds)->get(['id', 'title']);
    }
    public function userData()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
   
}
