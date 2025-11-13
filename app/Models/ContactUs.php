<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContactUs extends Model
{
    use HasFactory,SoftDeletes;



     public function queryRelation()
    {
        return $this->belongsTo(Query::class, 'query', 'id');    
    }   
}
