<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorBooking extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'sponsor_id',
        'amount',
        'payment_method',
        'session_id',
        'token',
        'email',
        'name',
        'number',
        'booking_id',  
        'base_amount',  
        'transferred_status',
        'assigned_by',
        'assigned_to',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function sponser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function sponsorUser()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }
    public function attendee()
    {
        return $this->belongsTo(Attndy::class ,'attendee_id');
    }
}
