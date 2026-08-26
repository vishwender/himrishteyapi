<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortListed extends Model
{
    protected $table = 'short_listed';

    protected $connection = 'application';

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'profile_id',
        'created_at',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'profile_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
