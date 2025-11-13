<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorporateUser extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'corporate_users';
    protected $guarded = ['id'];
    // protected $fillable = [
    //     'Name',
    //     'Email',
    //     'Mo',
    //     'password',
    //     'status',
    // ];
    

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categoryHasFields()
    {
        return $this->hasOne(Catrgoty_has_Field::class, 'category_id', 'category_id');
    }

    public function event() {
        return $this->belongsTo(Event::class);
    }

    public function userData() {
        return $this->belongsTo(User::class ,'user_id');
    }

}
