<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Event extends Model
{
    use HasFactory, SoftDeletes, HasRoles;

    protected $fillable = [
        'user_id',
        'category',
        'name',
        'country',
        'state',
        'city',
        'address',
        'short_info',
        'description',
        'offline_payment_instruction',
        // 'customer_care_number',
        'event_feature',
        'status',
        'house_full',
        'sms_otp_checkout',
        'date_range',
        'start_time',
        'end_time',
        'event_type',
        'map_code',
        'thumbnail',
        'image_1',
        'image_2',
        'image_3',
        'image_4',
        'youtube_url',
        'insta_url',
        'insta_thumb',
        'multi_qr',
        'meta_title',
        'meta_tag',
        'meta_description',
        'meta_keyword',
        'multi_scan',
        'online_att_sug',
        'offline_att_sug',
        'scan_detail',
        'ticket_system',
        'recommendation',
        'whatsapp_number',
        'entry_time',
        'online_booking',
        'agent_booking',
        'pos_booking',
        'complimentary_booking',
        'exhibition_booking',
        'amusement_booking',
        'sponsor_booking',
        'accreditation_booking',
        'card_url',
        'whts_note',
        'insta_whts_url',
        'transfer_ticket'
        // 'access_area',
        // 'modify_as',
        // 'pixel_code',
        // 'analytics_code',
    ];
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function reportingUser()
    {
        return $this->belongsTo(User::class, 'reporting_user_id'); // or whatever the field is
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function CategoryData()
    {
        return $this->belongsTo(Category::class, 'id', 'category');
    }

    public function Category()
    {
        return $this->belongsTo(Category::class, 'category');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function eventGate()
    {
        return $this->belongsTo(EventGate::class, 'event_id');
    }
    public function eventLayout()
    {
        return $this->belongsTo(CatLayout::class,'event_key', 'category_id');
    }
    public function IDCardLayout()
    {
        return $this->hasOne(CatLayout::class, 'category_id', 'event_key');
    }
  	
  	public function influencers()
    {
        return $this->belongsToMany(Influencer::class, 'event_influencers', 'event_id', 'influencer_id')
                    ->withTimestamps();
    }

    // public function seatConfig()
    // {
    //     return $this->belongsTo(SeatConfig::class,'event_id','id');
    // }
}
