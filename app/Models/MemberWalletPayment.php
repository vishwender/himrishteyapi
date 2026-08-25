<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberWalletPayment extends Model
{
    protected $table = 'member_wallet_payments';

    public $timestamps = false;

    protected $fillable = [

        'payment_date',

        'member_id',

        'amount',

        'payment_id',

        'remarks',

    ];
}
