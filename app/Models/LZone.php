<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LZone extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = ['venue_id', 'name', 'type', 'is_blocked'];

    public function tiers()
    {
        return $this->hasMany(LTier::class, 'zone_id');
    }

    public function venue()
    {
        return $this->belongsTo(LVenue::class, 'venue_id');
    }
}
