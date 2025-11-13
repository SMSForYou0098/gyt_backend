<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Booking extends Model
{
    use HasFactory,SoftDeletes;
    public function ticket()
    {
        return $this->belongsTo(Ticket::class)->whereNull('deleted_at');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function userData()
    {
        return $this->belongsTo(User::class, 'user_id');
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
        return $this->belongsTo(Attndy::class ,'attendee_id');
    }
    public function attendeess()
    {
        return $this->hasMany(Attndy::class ,'id','attendee_id');
    }
    public function paymentLog()
    {
        return $this->belongsTo(PaymentLog::class,'session_id','session_id');
    }
}
