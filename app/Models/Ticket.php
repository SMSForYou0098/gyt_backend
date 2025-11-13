<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'access_area' => 'array',
    ];
    protected $fillable = [
        'event_id',
        'name',
        'currency',
        'price',
        'ticket_quantity',
        'booking_per_customer',
        'description',
        'taxes',
        'sale',
        'sale_date',
        'sale_price',
        'sold_out',
        'booking_not_open',
        'ticket_template',
        'fast_filling',
        'status',
        'batch_id',
        'background_image',
        'promocode_ids',
        'access_area',
        'modify_as',
        'user_booking_limit',
        'allow_pos',
        'allow_agent',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    public function eventData()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
    public function agentBooking()
    {
        return $this->hasMany(Agent::class);
    }
    public function posBookings()
    {
        return $this->hasMany(PosBooking::class);
    }
    public function sponsorBookings()
    {
        return $this->hasMany(SponsorBooking::class);
    }
    public function agentAmusementBooking()
    {
        return $this->hasMany(AmusementAgentBooking::class);
    }
    public function PenddingBookings()
    {
        return $this->hasMany(PenddingBooking::class);
    }
    public function complimentaryBookings()
    {
        return $this->hasMany(ComplimentaryBookings::class);
    }

    public function AmusementPosBooking()
    {
        return $this->hasMany(AmusementPosBooking::class);
    }
    public function ExhibitionBooking()
    {
        return $this->hasMany(ExhibitionBooking::class);
    }
    public function AmusementBooking()
    {
        return $this->hasMany(AmusementBooking::class);
    }
    // public function promocode()
    // {
    //     return $this->hasMany(PromoCode::class);
    // }
    public function accessAreas()
    {
        return $this->hasMany(AccessArea::class, 'id', 'access_area');
    }

    // OR for array of IDs (access_area is stored as array or JSON in DB)
    public function getAccessAreaNamesAttribute()
    {
        return AccessArea::whereIn('id', $this->access_area)->pluck('name');
    }
}
