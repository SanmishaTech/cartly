<?php
// Test login flow

session_start();

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

echo "Testing login...\n";

$user = User::where('email', 'sanjeev@sanmisha.com')->first();

if (!$user) {
    echo "✗ User not found\n";
    exit(1);
}

echo "✓ User found: " . $user->name . "\n";

if ($user->verifyPassword('abcd123@')) {
    echo "✓ Password correct\n";
    
    $_SESSION['user_id'] = $user->id;
    $_SESSION['user_email'] = $user->email;
    $_SESSION['user_role'] = $user->role;
    
    echo "✓ Session set\n";
    echo "  user_id: " . $_SESSION['user_id'] . "\n";
    echo "  user_email: " . $_SESSION['user_email'] . "\n";
    echo "  user_role: " . $_SESSION['user_role'] . "\n";
    
    // Verify session persists
    echo "\n✓ Login successful!\n";
} else {
    echo "✗ Password incorrect\n";
    exit(1);
}
