<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;
    protected $fillable = [
        'tax_title',
        'rate_type',
        'status',
        'rate',
        'tax_type',
        'user_id', // Add user_id to the fillable array
    ];
}
