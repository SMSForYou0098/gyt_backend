<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class PromoCode extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'promo_codes';
    protected $guarded = ['id'];

    public function tickets()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
    public function AmusementBooking()
    {
        return $this->belongsTo(AmusementBooking::class);
    }
}
