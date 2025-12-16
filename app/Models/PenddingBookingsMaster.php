<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;


class PenddingBookingsMaster extends Model
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
        'payment_method',
        'session_id','booking_type',
    ];

    public function bookings(): HasMany
    {
        // Assuming that booking_id is an array of booking IDs
        return $this->hasMany(Booking::class, 'id', 'booking_id');
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
    public function paymentLog()
    {
        return $this->hasOne(PaymentLog::class,'session_id','session_id');
    }
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
