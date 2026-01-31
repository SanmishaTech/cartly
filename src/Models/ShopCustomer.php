<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Links users to shops as customers (not staff).
 * For CRM, analytics, wishlist, history. NOT for auth or permissions.
 */
class ShopCustomer extends Model
{
    protected $table = 'shop_customers';

    protected $fillable = [
        'shop_id',
        'user_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
