<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileViewed extends Model
{
    protected $table = 'profile_viewed';

    protected $connection = 'application';

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'viewed_profile_id',
        'created_at',
    ];
}
