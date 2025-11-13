<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventSeat extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'event_seats';
    protected $guarded = ['id'];

    public function seatConfig()
    {
        return $this->belongsTo(SeatConfig::class,'id','config_id');
    }
}
