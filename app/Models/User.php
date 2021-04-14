<?php

namespace App\Models;

use Core\Database\ORM\Model;

class User extends Model
{
    protected $table = "users";

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'age'
    ];

    protected $with = ['posts'];

    public function posts()
    {
        return $this->hasMany(\App\Models\Post::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(\App\Models\Profile::class, 'user_id');
    }
}
