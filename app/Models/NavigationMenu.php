<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NavigationMenu extends Model
{
    use HasFactory,SoftDeletes;

    public function Page()
    {
        return $this->belongsTo(Page::class);
    }
    public function MenuGroup()
    {
        return $this->belongsTo(MenuGroup::class);
    }
}
