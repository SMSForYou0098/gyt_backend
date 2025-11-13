<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Balance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', // Add user_id to the fillable array
        // Add other fillable fields if needed
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function assignBy()
    {
        return $this->belongsTo(User::class, 'assign_by', 'id');
    }
    public function accountManager()
    {
        return $this->belongsTo(User::class, 'account_manager_id', 'id');
    }

    public function shopData()
    {
        return $this->hasOne(Shop::class, 'user_id', 'user_id');
    }
    public function shopDataaaa()
    {
        return $this->hasOne(Shop::class, 'user_id', 'assign_by');
    }
}
