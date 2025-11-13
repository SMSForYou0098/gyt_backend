<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FooterMenu extends Model
{
    use HasFactory,SoftDeletes;

    public function FooterGroup()
    {
        return $this->belongsTo(FooterGroup::class);
    }
    public function pages()
    {
        return $this->belongsTo(Page::class, 'page_id'); // Specify the foreign key if needed
    }
}
