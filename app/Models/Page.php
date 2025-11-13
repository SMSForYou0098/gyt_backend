<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'meta_title',
        'meta_tag',
        'meta_description',
        'status'
    ];


    public function FooterMenu()
    {
        return $this->hasMany(FooterMenu::class, 'page_id');
    }

    public function NavigationMenu()
    {
        return $this->belongsTo(NavigationMenu::class);
    }

}
