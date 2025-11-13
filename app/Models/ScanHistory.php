<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScanHistory extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'user_id',
        'scanner_id',
        'token',
        'booking_source',
        'scan_time',
        'count',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function scanner()
    {
        return $this->belongsTo(User::class, 'scanner_id');
    }
}
