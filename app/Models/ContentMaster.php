<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentMaster extends Model
{
    use HasFactory,SoftDeletes;

     protected $table = 'content_masters';

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the content
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
