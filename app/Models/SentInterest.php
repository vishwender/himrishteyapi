<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentInterest extends Model
{
    protected $table = 'sent_interests';

    protected $connection = 'application';

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'profile_id',
        'status',
        'created_at',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'profile_id' => 'integer',
        'created_at' => 'datetime',
    ];
}
