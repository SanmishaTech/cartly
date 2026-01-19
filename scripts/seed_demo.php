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

// Seed a basic package
$package = Package::firstOrCreate([
    'name' => 'Demo Package',
], [
    'cost_1_month' => 0,
    'cost_3_month' => 0,
    'cost_6_month' => 0,
    'cost_12_month' => 0,
    'features' => ['products' => 100, 'staff' => 3],
    'active' => true,
]);

function seedShop($slug, $brandName, $domain, $package, $theme, $adminEmail) {
    $shop = Shop::firstOrCreate(['slug' => $slug], [
        'brand_name' => $brandName,
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
    Subscription::firstOrCreate([
        'shop_id' => $shop->id,
        'package_id' => $package->id,
    ], [
        'starts_at' => $now,
        'expires_at' => $now->copy()->addDays(30),
        'trial_days' => 7,
        'status' => 'active',
        'renewal_mode' => 'manual',
    ]);

    User::firstOrCreate(['email' => $adminEmail], [
        'password' => 'abcd123@',
        'name' => "{$brandName} Admin",
        'role' => 'admin',
        'shop_id' => $shop->id,
        'status' => 'active',
    ]);

    echo "Seeded {$brandName} with domain '{$domain}' and theme '{$theme}'.\n";
}

$appDomain = $_ENV['APP_DOMAIN'] ?? 'cartly.test';
seedShop('demo1', 'Demo 1', "demo1.{$appDomain}", $package, 'default', 'demo1@demo.com');
seedShop('demo2', 'Demo 2', "demo2.{$appDomain}", $package, 'modern', 'demo2@demo.com');

echo "All demo shops seeded.\n";
