<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorporateBooking extends Model
{
    use HasFactory,SoftDeletes;
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function CorporateUser()
    {
        return $this->belongsTo(CorporateUser::class , 'number', 'Mo');
    }
    public function ticketData()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id','id');
    }
}
