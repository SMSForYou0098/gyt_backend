<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LSeat extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = ['row_id', 'number', 'status', 'is_booked', 'price'];

    public function row()
    {
        return $this->belongsTo(LRow::class, 'row_id');
    }
}
