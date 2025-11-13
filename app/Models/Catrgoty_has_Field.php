<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Catrgoty_has_Field extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['category_id', 'custom_fields_id'];
    // protected $casts = [

    //     'custom_fields_id' => 'array', // Cast JSON to array
    // ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function customFields()
    {
        return CustomField::whereIn('id', explode(',', $this->custom_fields_id))->get();
    }

    public function customFieldsDataa()
    {
        return $this->hasMany(CustomField::class, 'id', 'custom_fields_id')->whereIn('id', explode(',', $this->custom_fields_id));
    }

    public function customFieldsDataaa()
    {
        return $this->hasMany(CustomField::class, 'id', 'category_id'); // Adjust 'CustomField' to match the actual model name
    }

}
