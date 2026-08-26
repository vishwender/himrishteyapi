<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileLike extends Model
{
    protected $table = 'profile_like';

    public $timestamps = false;

    protected $connection = 'application';

    protected $fillable = [
        'user_id',
        'like_profile_id',
        'status',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'like_profile_id' => 'integer',
        'status' => 'integer',
    ];
}
