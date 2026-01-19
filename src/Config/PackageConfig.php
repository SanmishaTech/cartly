<?php

namespace App\Config;

class PackageConfig
{
    public static function features(): array
    {
        return [
            'collections' => 'No. of Collections',
            'products' => 'No. of Products',
            'staff_users' => 'No. of Staff Users',
            'storage_gb' => 'Storage (GB)',
        ];
    }
}
