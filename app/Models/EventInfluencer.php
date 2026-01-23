<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventInfluencer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'influencer_id',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function influencer()
    {
        return $this->belongsTo(Influencer::class);
    }
}
