<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenddingBooking extends Model
{
    use HasFactory,SoftDeletes;
     protected $fillable = [
        'name',
        'email',
        'number',
        'amount',
        'session_id',
        'status',
        'other',
        'payment_id',
        'payment_method',
       	'inf_id'
    ];
    

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // public function agent()
    // {
    //     return $this->belongsTo(User::class, 'agent_id');
    // }
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
        return $this->hasOne(PaymentLog::class,'session_id','session_id');
    }
}
