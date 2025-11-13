<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmusementAgentMasterBooking extends Model
{
    use HasFactory,SoftDeletes;

    public function bookings(): HasMany
    {
        return $this->hasMany(AmusementAgentBooking::class, 'id', 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
