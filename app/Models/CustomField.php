<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomField extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['field_name', 'field_type', 'field_value','field_options','sr_no'];

    public function CategoryData()
    {
        return $this->hasMany(Category::class);
    }
}
