<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopDomain extends Model
{
    protected $table = 'shop_domains';
    protected $fillable = ['shop_id', 'domain', 'is_primary', 'is_temp', 'verified_at', 'status'];
    protected $casts = [
        'is_primary' => 'boolean',
        'is_temp' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
