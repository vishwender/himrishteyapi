<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeleteProfileRequest extends Model
{
    protected $table = 'delete_profile_request';

    public $timestamps = false;

    protected $connection = 'application';

    protected $fillable = [
        'user_id',
        'reason',
        'request_by',
        'date',
        'status',
    ];
}
