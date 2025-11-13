<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorMasterBooking extends Model
{
    use HasFactory,SoftDeletes;
    
    protected $casts = [
        'booking_id' => 'array',
    ];
    
    public function bookings(): HasMany
    {
        return $this->hasMany(SponsorBooking::class, 'id', 'booking_id');
    }
    // Accessor to decode booking_id from JSON
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }
}
