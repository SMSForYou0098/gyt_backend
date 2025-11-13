<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SeatConfig extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'seat_configs';
    protected $guarded = ['id'];

    // public function seatConfig()
    // {
    //     return $this->belongsTo(Event::class,'id','event_id');
    // }

    public function EventSeat()
    {
        return $this->hasMany(EventSeat::class,'config_id','id');
    }
}
