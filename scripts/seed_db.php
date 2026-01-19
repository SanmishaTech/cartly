<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use App\Models\Shop;
use App\Models\ShopDomain;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_ENV['DB_DRIVER'] ?? $_ENV['DB_CONNECTION'] ?? 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'database'  => $_ENV['DB_DATABASE'] ?? 'cartly',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'port'      => (int)($_ENV['DB_PORT'] ?? 3306),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => ''
]);
$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Seed demo packages
$starterPackage = Package::firstOrCreate([
    'name' => 'Starter',
], [
    'cost_1_month' => 0,
    'cost_3_month' => 0,
    'cost_6_month' => 0,
    'cost_12_month' => 0,
    'features' => ['products' => 100, 'staff' => 3],
    'active' => true,
]);

$proPackage = Package::firstOrCreate([
    'name' => 'Pro',
], [
    'cost_1_month' => 999,
    'cost_3_month' => 2799,
    'cost_6_month' => 5199,
    'cost_12_month' => 9999,
    'features' => ['products' => 1000, 'staff' => 15],
    'active' => true,
]);

function seedShop($slug, $shopName, $domain, $package, $theme, $adminEmail) {
    $shop = Shop::firstOrCreate(['slug' => $slug], [
        'shop_name' => $shopName,
        'status' => 'active',
        'theme' => $theme,
    ]);

    ShopDomain::firstOrCreate([
        'shop_id' => $shop->id,
        'domain' => $domain,
    ], [
        'is_primary' => true,
        'is_temp' => true,
        'verified_at' => Carbon::now(),
        'status' => 'active',
    ]);

    $now = Carbon::now();
    $expiresAt = $now->copy()->addDays(30);
    Subscription::firstOrCreate([
        'shop_id' => $shop->id,
        'package_id' => $package->id,
    ], [
        'starts_at' => $now,
        'expires_at' => $expiresAt,
        'next_renewal_at' => $expiresAt,
        'trial_days' => 7,
        'type' => 'package',
        'renewal_mode' => 'manual',
    ]);

    User::firstOrCreate(['email' => $adminEmail], [
        'password' => 'abcd123@',
        'name' => "{$shopName} Admin",
        'role' => 'admin',
        'shop_id' => $shop->id,
        'status' => 'active',
    ]);

    echo "Seeded {$shopName} with domain '{$domain}' and theme '{$theme}'.\n";
}

// Seed root and helpdesk users
echo "Seeding root and helpdesk users...\n";
$users = [
    [
        'email' => 'root@demo.com',
        'name' => 'Root User',
        'role' => 'root',
    ],
    [
        'email' => 'helpdesk@demo.com',
        'name' => 'Helpdesk User',
        'role' => 'helpdesk',
    ],
];

foreach ($users as $data) {
    $existing = User::where('email', $data['email'])->first();
    if ($existing) {
        echo "✓ {$data['role']} user already exists ({$data['email']})\n";
        continue;
    }

    User::create([
        'email' => $data['email'],
        'password' => 'abcd123@',
        'name' => $data['name'],
        'role' => $data['role'],
        'shop_id' => null,
        'status' => 'active',
    ]);

    echo "✓ {$data['role']} user created successfully\n";
    echo "  Email: {$data['email']}\n";
    echo "  Password: abcd123@\n";
}

// Seed demo shops with subscriptions and admins
$appDomain = $_ENV['APP_DOMAIN'] ?? 'cartly.test';
seedShop('demo1', 'Demo 1', "demo1.{$appDomain}", $starterPackage, 'default', 'demo1@demo.com');
seedShop('demo2', 'Demo 2', "demo2.{$appDomain}", $proPackage, 'modern', 'demo2@demo.com');

echo "All seeds completed.\n";
