<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LSection extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['tier_id', 'name', 'is_blocked'];

    public function rows()
    {
        return $this->hasMany(LRow::class, 'section_id');
    }

    public function tier()
    {
        return $this->belongsTo(LTier::class, 'tier_id');
    }
}
