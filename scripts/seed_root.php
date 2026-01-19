<?php

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use App\Models\User;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_ENV['DB_CONNECTION'] ?? 'mysql',
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

