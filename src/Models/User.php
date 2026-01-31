<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'email',
        'name',
        'password',
        'global_role',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Hash password before saving (skip for null, e.g. OAuth-only shoppers)
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value === null ? null : password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Verify password (returns false if user has no password, e.g. OAuth-only)
     */
    public function verifyPassword($password)
    {
        return $this->password !== null && password_verify($password, $this->password);
    }

    /**
     * Shop memberships (permissions): user_id, shop_id, role (owner|admin|staff)
     */
    public function shopUsers()
    {
        return $this->hasMany(ShopUser::class);
    }

    /**
     * Shops this user can manage (via shop_users)
     */
    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'shop_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * OAuth accounts linked to this user
     */
    public function oauthAccounts()
    {
        return $this->hasMany(UserOAuthAccount::class);
    }

    /**
     * Shop-customer links (CRM/history per shop). NOT for auth or permissions.
     */
    public function shopCustomers()
    {
        return $this->hasMany(ShopCustomer::class);
    }

    /**
     * Check if user is root (global role)
     */
    public function isRoot()
    {
        return $this->global_role === 'root';
    }

    /**
     * Check if user is helpdesk (global role)
     */
    public function isHelpdesk()
    {
        return $this->global_role === 'helpdesk';
    }

    /**
     * Check if user is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if user can manage root panel
     */
    public function canAccessRoot()
    {
        return $this->isRoot() && $this->isActive();
    }

    /**
     * Resolve effective role for user in shop context (for authorization).
     * Returns: 'root' | 'helpdesk' | 'owner' | 'admin' | 'staff' | null (no access).
     */
    public function getEffectiveRoleForShop(int $shopId): ?string
    {
        if ($this->global_role === 'root' || $this->global_role === 'helpdesk') {
            return $this->global_role;
        }
        $membership = ShopUser::where('user_id', $this->id)->where('shop_id', $shopId)->first();

        return $membership ? $membership->role : null;
    }

    /**
     * Check if user can manage this shop (settings, staff, products, orders, setup).
     */
    public function canManageShop($shopId): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        if ($this->global_role === 'root' || $this->global_role === 'helpdesk') {
            return true;
        }

        return ShopUser::where('user_id', $this->id)
            ->where('shop_id', (int) $shopId)
            ->whereIn('role', [ShopUser::ROLE_OWNER, ShopUser::ROLE_ADMIN, ShopUser::ROLE_STAFF])
            ->exists();
    }

    /**
     * Get first shop this user can manage (for session default).
     */
    public function getFirstManagedShopId(): ?int
    {
        if ($this->global_role === 'root' || $this->global_role === 'helpdesk') {
            $shop = Shop::first();

            return $shop ? (int) $shop->id : null;
        }
        $membership = ShopUser::where('user_id', $this->id)->first();

        return $membership ? (int) $membership->shop_id : null;
    }
}
