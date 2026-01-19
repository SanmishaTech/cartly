<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'subscriptions';
    protected $fillable = [
        'shop_id',
        'package_id',
        'starts_at',
        'expires_at',
        'trial_days',
        'status',
        'renewal_mode',
        'payment_method',
        'price_paid',
        'currency',
        'billing_period_months',
        'next_renewal_at',
    ];
    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'trial_days' => 'integer',
        'next_renewal_at' => 'date',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}
