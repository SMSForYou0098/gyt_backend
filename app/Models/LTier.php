<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LTier extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['zone_id', 'name', 'is_blocked', 'price'];

    public function sections()
    {
        return $this->hasMany(LSection::class, 'tier_id');
    }

    public function zone()
    {
        return $this->belongsTo(LZone::class, 'zone_id');
    }
}
