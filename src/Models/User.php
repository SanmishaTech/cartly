<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $fillable = [
        'email',
        'password',
        'name',
        'role',
        'shop_id',
        'status',
        'two_factor_enabled',
        'two_factor_secret',
        'last_login_at',
    ];
    
    protected $hidden = [
        'password',
        'two_factor_secret',
    ];
    
    protected $casts = [
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
    ];

    /**
     * Hash password before saving
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
    }

    /**
     * Verify password
     */
    public function verifyPassword($password)
    {
        return password_verify($password, $this->password);
    }

    /**
     * Get associated shop
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Check if user is root
     */
    public function isRoot()
    {
        return $this->role === 'root';
    }

    /**
     * Check if user is helpdesk
     */
    public function isHelpdesk()
    {
        return $this->role === 'helpdesk';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is operations
     */
    public function isOperations()
    {
        return $this->role === 'operations';
    }

    /**
     * Check if user is shopper
     */
    public function isShopper()
    {
        return $this->role === 'shopper';
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
     * Check if user can manage shop
     */
    public function canManageShop($shopId)
    {
        if ($this->isRoot() || $this->isHelpdesk()) {
            return true;
        }
        return $this->shop_id === $shopId && $this->isActive();
    }
}
