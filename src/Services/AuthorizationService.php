<?php

namespace App\Services;

class AuthorizationService
{
    public const ROLE_ROOT = 'root';
    public const ROLE_HELPDESK = 'helpdesk';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OPERATIONS = 'operations';
    public const ROLE_SHOPPER = 'shopper';

    public const PERMISSION_ROOT_ACCESS = 'root.access';
    public const PERMISSION_ADMIN_ACCESS = 'admin.access';
    public const PERMISSION_PACKAGES_MANAGE = 'packages.manage';
    public const PERMISSION_SHOPS_MANAGE = 'shops.manage';
    public const PERMISSION_SUPPORT_SUDO = 'support.sudo';

    private array $rolePermissions = [
        self::ROLE_ROOT => ['*'],
        self::ROLE_ADMIN => [
            self::PERMISSION_ADMIN_ACCESS,
        ],
        self::ROLE_HELPDESK => [
            self::PERMISSION_ADMIN_ACCESS,
            self::PERMISSION_SUPPORT_SUDO,
        ],
        self::ROLE_OPERATIONS => [
            self::PERMISSION_ADMIN_ACCESS,
        ],
        self::ROLE_SHOPPER => [],
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
