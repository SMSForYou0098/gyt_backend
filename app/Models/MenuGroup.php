<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuGroup extends Model
{
    use HasFactory,SoftDeletes;


    public function NavigationMenu()
    {
        return $this->hasMany(NavigationMenu::class ,'menu_group_id','id')->with('Page')->orderBy('sr_no');
    }

    // public function navigationMenu()
    // {
    //     return $this->hasMany(NavigationMenu::class, 'menu_group_id', 'id')
    //                 ->with(['Page' => function($query) {
    //                     $query->whereNotNull('id'); // Fetch the page if it's not null
    //                 }])
    //                 ->orderBy('sr_no');
    // }
}
