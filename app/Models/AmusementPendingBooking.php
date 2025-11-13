<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmusementPendingBooking extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'amusement_pending_bookings';
    protected $guarded = ['id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promocode()
    {
        return $this->belongsTo(PromoCode::class);
    }
    public function attendee()
    {
        return $this->belongsTo(Attndy::class);
    }
    public function paymentLog()
    {
        return $this->belongsTo(PaymentLog::class,'session_id','session_id');
    }
}
