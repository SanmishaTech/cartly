<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Dotenv\Dotenv;
use App\Config\PackageConfig;

// Load environment
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup Eloquent
$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $_ENV['DB_HOST'],
    'port'      => $_ENV['DB_PORT'],
    'database'  => $_ENV['DB_DATABASE'],
    'username'  => $_ENV['DB_USERNAME'],
    'password'  => $_ENV['DB_PASSWORD'],
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Import the Package model
use App\Models\Package;

// Create test packages
echo "Creating test packages...\n";

Package::truncate(); // Clear existing packages

$packagesSeed = [
    [
        'name' => 'Starter',
        'cost_1_month' => 29.99,
        'cost_3_month' => 79.99,
        'cost_6_month' => 149.99,
        'cost_12_month' => 279.99,
        'features' => ['collections' => 5, 'products' => 100, 'staff_users' => 2, 'storage_gb' => 5],
        'active' => true,
    ],
    [
        'name' => 'Professional',
        'cost_1_month' => 59.99,
        'cost_3_month' => 169.99,
        'cost_6_month' => 329.99,
        'cost_12_month' => 629.99,
        'features' => ['collections' => 20, 'products' => 1000, 'staff_users' => 10, 'storage_gb' => 50],
        'active' => true,
    ],
    [
        'name' => 'Enterprise',
        'cost_1_month' => 129.99,
        'cost_3_month' => 359.99,
        'cost_6_month' => 699.99,
        'cost_12_month' => 1299.99,
        'features' => ['collections' => 100, 'products' => 10000, 'staff_users' => 50, 'storage_gb' => 500],
        'active' => false,
    ],
];

foreach ($packagesSeed as $payload) {
    Package::create($payload);
}

$packages = Package::all();
echo "Created " . count($packages) . " packages\n\n";

echo "Packages in database:\n";
foreach ($packages as $p) {
    echo "- {$p->name}: 1mo \${$p->cost_1_month}, 3mo \${$p->cost_3_month}, 6mo \${$p->cost_6_month}, 12mo \${$p->cost_12_month} (Active: " . ($p->active ? 'Yes' : 'No') . ")\n";
    if ($p->features) {
        foreach (PackageConfig::features() as $key => $label) {
            $value = $p->features[$key] ?? 0;
            echo "  • {$label}: {$value}\n";
        }
    }
}

echo "\nTest completed successfully!\n";
