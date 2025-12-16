<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Contracts\Role;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];
    protected $fillable = ['name', 'email', 'number', 'password', 'status', 'reporting_user'];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];


 public function orgType()
    {
        return $this->belongsTo(Query::class, 'org_type_of_company', 'id');
    }

    public function reportingUser()
    {
        return $this->belongsTo(User::class, 'reporting_user');
    }
    public function usersUnder()
    {
        return $this->hasMany(User::class, 'reporting_user');
    }
    public function agentBooking()
    {
        return $this->hasMany(Booking::class, 'agent_id');
    }
    public function agentBookingNew()
    {
        return $this->hasMany(Agent::class, 'agent_id');
    }
    public function sponsorBookingNew()
    {
        return $this->hasMany(SponsorBooking::class, 'sponsor_id');
    }
    public function AccreditationBookingNew()
    {
        return $this->hasMany(AccreditationBooking::class, 'accreditation_id');
    }
    public function agentAmusementBookingNew()
    {
        return $this->hasMany(AmusementAgentBooking::class, 'agent_id');
    }
    public function PosBooking()
    {
        return $this->hasMany(PosBooking::class);
    }
    public function AmusementPosBooking()
    {
        return $this->hasMany(AmusementPosBooking::class);
    }
    public function ExhibitionBooking()
    {
        return $this->hasMany(ExhibitionBooking::class);
    }
    public function AmusementBooking()
    {
        return $this->hasMany(AmusementBooking::class);
    }
    public function EmailTemplate()
    {
        return $this->belongsTo(PosBooking::class);
    }
    public function booking()
    {
        return $this->hasMany(Booking::class);
    }
    public function PenddingBookings()
    {
        return $this->hasMany(PenddingBooking::class);
    }
    public function masterBooking()
    {
        return $this->belongsTo(MasterBooking::class);
    }
    public function balance()
    {
        return $this->hasMany(Balance::class);
    }
    public function shop()
    {
        return $this->hasOne(Shop::class, 'user_id', 'id');
    }
    public function tickets()
    {
        return $this->hasMany(Booking::class);
    }
    public function events()
    {
        return $this->hasMany(Event::class);
    }
    public function AgentEvent()
    {
        return $this->hasMany(AgentEvent::class);
    }
    public function smsConfig()
    {
        return $this->hasMany(SmsConfig::class);
    }
    public function whatsappConfig()
    {
        return $this->hasMany(WhatsappConfigurations::class);
    }
    public function complimentaryBookings()
    {
        return $this->hasMany(ComplimentaryBookings::class);
    }
    public function eventsOrg()
    {
        return $this->hasMany(Event::class, 'user_id'); // adjust if key name differs
    }

      public function latestLoginHistory()
    {
        return $this->hasOne(LoginHistory::class, 'user_id')->latest();
    }

    //   public function orgType()
    // {
    //     return $this->hasMany(Query::class);
    // }
}
