<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Sanctum tokens are stored in the central database.
     *
     * The application/member data is stored in the
     * dynamically selected application database.
     */
    protected $connection = 'mariadb';

    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'tokenable_id',
        'tokenable_type',
        'application_id',
    ];
}
