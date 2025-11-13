<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhonePe extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'client_id',        // This is from "Client Id" in dashboard
        'secret',    // This is from "Client Secret" in dashboard
        'status'    // This is from "Client Version" in dashboard
    ];
}
