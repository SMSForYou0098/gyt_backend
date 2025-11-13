<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FooterGroup extends Model
{
    use HasFactory,SoftDeletes;

    public function FooterMenu()
    {
        return $this->hasMany(FooterMenu::class);
    }
    
}
