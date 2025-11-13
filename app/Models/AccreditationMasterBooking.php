<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccreditationMasterBooking extends Model
{
    use HasFactory,SoftDeletes;

    protected $casts = [
        'booking_id' => 'array',
    ];
    
    public function bookings(): HasMany
    {
        return $this->hasMany(AccreditationBooking::class, 'id', 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'accreditation_id');
    }
}
