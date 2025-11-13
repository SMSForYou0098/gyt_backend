<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmusementBooking extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'amusement_bookings';
    protected $guarded = ['id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function userData()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function promocode()
    {
        return $this->belongsTo(PromoCode::class);
    }
    public function attendee()
    {
        return $this->belongsTo(Attndy::class ,'attendee_id');
    }
    public function paymentLog()
    {
        return $this->belongsTo(PaymentLog::class,'session_id','session_id');
    }
}
