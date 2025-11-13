<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmusementMasterBooking extends Model
{
    use HasFactory,SoftDeletes;
    // protected $table = 'amusement_master_bookings';
    protected $guarded = ['id'];

    public function bookings(): HasMany
    {
        return $this->hasMany(AmusementBooking::class, 'id', 'booking_id');
    }

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
        return $this->belongsTo(PaymentLog::class,'session_id','session_id');
    }
}
