<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuccessStory extends Model
{
    protected $table = 'success_stories';

    public $timestamps = false;

    protected $connection = 'application';

    protected $fillable = [
        'groom_name',
        'bride_name',
        'detail',
        'photo',
        'user_id',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
    ];

    /**
     * Check whether the story has been approved.
     */
    public function isApproved(): bool
    {
        return (int) $this->status === 1;
    }

    /**
     * Get photo URL.
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (empty($this->photo)) {
            return null;
        }

        return url('photos/ss/' . $this->photo);
    }
}
