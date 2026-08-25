<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberWallet extends Model
{
    protected $table = 'member_wallet';

    /*
    |--------------------------------------------------------------------------
    | Legacy wallet table
    |--------------------------------------------------------------------------
    |
    | The legacy table does not use Laravel's normal updated_at column.
    |
    */

    public $timestamps = false;

    protected $fillable = [
        'member_id',
        'wallet_balance',
        'amount_deducted',
        'amount_added',
        'created_at',
        'update_at',
        'added_by',
    ];

    /**
     * Use the currently resolved application connection.
     */
    protected $connection = 'application';

    /**
     * Convert legacy wallet values to usable numbers.
     */
    public function getBalanceValueAttribute(): float
    {
        return is_numeric($this->wallet_balance)
            ? (float) $this->wallet_balance
            : 0;
    }

    public function getAddedAmountAttribute(): float
    {
        return is_numeric($this->amount_added)
            ? (float) $this->amount_added
            : 0;
    }

    public function getDeductedAmountAttribute(): float
    {
        return is_numeric($this->amount_deducted)
            ? (float) $this->amount_deducted
            : 0;
    }
}
