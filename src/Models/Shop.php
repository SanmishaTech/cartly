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
        'default_seo_title',
        'default_seo_description',
        'seo_keywords',
        'canonical_url',
        'meta_robots',
        'home_seo',
        'og_title',
        'og_description',
        'logo_path',
        'favicon_path',
        'social_image_path',
        'twitter_card_type',
        'hero_type',
        'hero_settings',
        'sitemap_enabled',
        'structured_data',
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
