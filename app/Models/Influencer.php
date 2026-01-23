<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Influencer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'bio',
        'social_media_handle',
        'platform',
        'followers',
        'status',
    ];

    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_influencers', 'influencer_id', 'event_id')
                    ->withTimestamps();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'inf_id');
    }

    public function masterBookings()
    {
        return $this->hasMany(MasterBooking::class, 'inf_id');
    }
}
