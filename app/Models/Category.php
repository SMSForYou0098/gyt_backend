<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory,SoftDeletes;

    public function EventData()
    {
        return $this->hasMany(Event::class ,'category','id');
    }
    public function CustomFieldData()
    {
        return $this->hasMany(CustomField::class);
    }

    public function CatrgotyhasField()
    {
        return $this->hasOne(Catrgoty_has_Field::class);
    }



}
