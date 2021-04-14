<?php

namespace App\Models;

use Core\Database\ORM\Model;

class Post extends Model
{
    protected $table = "posts";

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'body',
    ];
    
    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
