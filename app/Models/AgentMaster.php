<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentMaster extends Model
{
    use HasFactory,SoftDeletes;

    protected $casts = [
        'booking_id' => 'array',
    ];

    protected $fillable = [
        'booking_id', // or 'bookingIds' based on your naming convention
        'user_id',
        'order_id',
        'amount',
        'discount',
        'payment_method','session_id','agent_id','transferred_status','assigned_by','assigned_to'
    ];

    public function bookings(): HasMany
    {
        // Assuming that booking_id is an array of booking IDs
        return $this->hasMany(Agent::class, 'id', 'booking_id');
    }
    // Accessor to decode booking_id from JSON
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

}
