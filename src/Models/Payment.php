<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'payments';
    protected $fillable = [
        'shop_id',
        'subscription_id',
        'payment_id',
        'order_id',
        'amount',
        'currency',
        'status',
        'method',
        'razorpay_response',
        'razorpay_signature',
        'paid_at',
        'refunded_at',
        'notes',
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
