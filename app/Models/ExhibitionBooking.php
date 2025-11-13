<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExhibitionBooking extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'exhibition_bookings';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function attendee()
    {
        return $this->belongsTo(Attndy::class ,'attendee_id');
    }
}
