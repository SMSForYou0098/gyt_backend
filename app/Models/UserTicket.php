<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserTicket extends Model
{
    use HasFactory,SoftDeletes;
    protected $casts = [
        'ticket_id' => 'array'
    ];
    protected $fillable = ['user_id', 'event_id', 'ticket_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket()
    {
        return $this->hasMany(Ticket::class);
    }
}
