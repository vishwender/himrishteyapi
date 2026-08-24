<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberPhoto extends Model
{
    protected $table = 'member_photos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'photo',
        'photo_approved',
        'photo_privacy',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(
            Member::class,
            'member_id',
            'id'
        );
    }
}
