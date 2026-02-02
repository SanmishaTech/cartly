<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shop extends Model
{
    protected $table = 'shops';
    protected $fillable = [
        'slug',
        'shop_name',
        'status',
    ];

    public function metadata(): HasOne
    {
        return $this->hasOne(ShopMetadata::class);
    }

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

    public function shopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Customers who have been seen in this shop (CRM/history). NOT for auth.
     */
    public function shopCustomers(): HasMany
    {
        return $this->hasMany(ShopCustomer::class);
    }

    public function emailSettings(): HasOne
    {
        return $this->hasOne(ShopEmailSettings::class);
    }

    public function getShopDescriptionAttribute(): ?string
    {
        return $this->metadata?->shop_description;
    }

    public function getAddressLine1Attribute(): ?string
    {
        return $this->metadata?->address_line1;
    }

    public function getAddressLine2Attribute(): ?string
    {
        return $this->metadata?->address_line2;
    }

    public function getCityAttribute(): ?string
    {
        return $this->metadata?->city;
    }

    public function getStateAttribute(): ?string
    {
        return $this->metadata?->state;
    }

    public function getPostalCodeAttribute(): ?string
    {
        return $this->metadata?->postal_code;
    }

    public function getCountryAttribute(): ?string
    {
        return $this->metadata?->country;
    }

    public function getLogoPathAttribute(): ?string
    {
        return $this->metadata?->logo_path;
    }

    public function getFaviconPathAttribute(): ?string
    {
        return $this->metadata?->favicon_path;
    }

    public function getHeroTypeAttribute(): ?string
    {
        return $this->metadata?->hero_type;
    }

    public function getHeroSettingsAttribute(): ?array
    {
        $settings = $this->metadata?->hero_settings;
        return is_array($settings) ? $settings : null;
    }

    public function getThemeAttribute(): ?string
    {
        return $this->metadata?->theme;
    }

    public function getThemeConfigAttribute()
    {
        return $this->metadata?->theme_config;
    }
}
