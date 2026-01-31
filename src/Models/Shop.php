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
        'shop_description',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'status',
        'theme',
        'theme_config',
        'logo_path',
        'favicon_path',
        'hero_type',
        'hero_settings',
    ];

    protected $casts = [
        'hero_settings' => 'array',
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
}
