<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    protected $table = 'shops';
    protected $fillable = [
        'slug',
        'shop_name',
        'shop_description',
        'status',
        'theme',
        'theme_config',
        'logo_path',
        'favicon_path',
        'hero_type',
        'hero_settings',
        'sitemap_enabled',
    ];

    public function domains(): HasMany
    {
        return $this->hasMany(ShopDomain::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
}
