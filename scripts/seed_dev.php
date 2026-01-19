<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\Shop;
use App\Models\ShopDomain;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$capsule = new Illuminate\Database\Capsule\Manager();
$capsule->addConnection([
    'driver'    => getenv('DB_DRIVER') ?: 'mysql',
    'host'      => getenv('DB_HOST') ?: '127.0.0.1',
    'database'  => getenv('DB_DATABASE') ?: 'cartly',
    'username'  => getenv('DB_USERNAME') ?: 'root',
    'password'  => getenv('DB_PASSWORD') ?: '',
    'port'      => getenv('DB_PORT') ?: 3306,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => ''
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$shop = Shop::firstOrCreate(['slug' => 'demo'], ['brand_name' => 'Demo Shop']);
ShopDomain::firstOrCreate([
    'shop_id' => $shop->id,
    'domain' => 'localhost',
], [
    'is_primary' => true,
    'is_temp' => true,
    'status' => 'active',
]);

echo "Seeded demo shop with domain 'localhost'.\n";
