<?php

namespace App\Models;

use Core\Database\ORM\Model;

class Profile extends Model
{
    protected $table = "profiles";

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'country',
        'user_id'
    ];

    protected $with = [];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
