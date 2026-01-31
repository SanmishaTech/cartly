<?php

namespace App\Services;

class AuthorizationService
{
    /** Platform-level roles (users.global_role) */
    public const ROLE_ROOT = 'root';
    public const ROLE_HELPDESK = 'helpdesk';

    /** Shop-level roles (shop_users.role) */
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    public const PERMISSION_DASHBOARD_ACCESS = 'dashboard.access';
    public const PERMISSION_PACKAGES_MANAGE = 'packages.manage';
    public const PERMISSION_SHOPS_MANAGE = 'shops.manage';
    public const PERMISSION_USERS_MANAGE = 'users.manage';
    public const PERMISSION_SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';
    public const PERMISSION_SUPPORT_SUDO = 'support.sudo';
    public const PERMISSION_SETUP_ACCESS = 'setup.access';

    private array $rolePermissions = [
        self::ROLE_ROOT => [
            self::PERMISSION_DASHBOARD_ACCESS,
            self::PERMISSION_PACKAGES_MANAGE,
            self::PERMISSION_SHOPS_MANAGE,
            self::PERMISSION_USERS_MANAGE,
            self::PERMISSION_SUBSCRIPTIONS_MANAGE,
            self::PERMISSION_SUPPORT_SUDO,
        ],
        self::ROLE_HELPDESK => [
            self::PERMISSION_DASHBOARD_ACCESS,
            self::PERMISSION_SUPPORT_SUDO,
        ],
        self::ROLE_OWNER => [
            self::PERMISSION_DASHBOARD_ACCESS,
            self::PERMISSION_SETUP_ACCESS,
        ],
        self::ROLE_ADMIN => [
            self::PERMISSION_DASHBOARD_ACCESS,
        ],
        self::ROLE_STAFF => [
            self::PERMISSION_DASHBOARD_ACCESS,
        ],
    ];

    public function roleHasPermission(?string $role, string $permission): bool
    {
        if (!$role) {
            return false;
        }

        $permissions = $this->rolePermissions[$role] ?? [];
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    public function getPermissionsForRole(?string $role): array
    {
        if (!$role) {
            return [];
        }

        return $this->rolePermissions[$role] ?? [];
    }
}
