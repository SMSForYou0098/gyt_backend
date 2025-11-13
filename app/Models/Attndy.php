<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attndy extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'attndies';
    protected $guarded = ['id'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categoryHasFields()
    {
        return $this->hasOne(Catrgoty_has_Field::class, 'category_id', 'category_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function userData()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function agentData()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
    public function agentUser()
    {
        return $this->belongsTo(User::class, 'agent_id', 'id');
    }
  
    public function booking()
    {
        return $this->hasOne(Booking::class, 'attendee_id', 'id');
    }

    public function agentBooking()
    {
        return $this->hasOne(Agent::class, 'attendee_id', 'id');
    }

    // direct ticket relation (priority Booking > Agent)
    public function ticket()
    {
        if ($this->booking) {
            return $this->booking->ticket();
        }
        if ($this->agentBooking) {
            return $this->agentBooking->ticket();
        }
        return null;
    }
}
