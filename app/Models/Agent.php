<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'agents';
    protected $guarded = ['id'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function agent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function agentUser()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
    public function attendee()
    {
        return $this->belongsTo(Attndy::class ,'attendee_id');
    }
}
