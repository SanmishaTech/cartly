<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Blueprint;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => $_ENV['DB_DRIVER'] ?? $_ENV['DB_CONNECTION'] ?? getenv('DB_DRIVER') ?? getenv('DB_CONNECTION') ?? 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? '127.0.0.1',
    'database'  => $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?? 'cartly',
    'username'  => $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? '',
    'port'      => (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 3306),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => ''
]);
$capsule->setEventDispatcher(new Dispatcher(new Container));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$schema = $capsule->getConnection()->getSchemaBuilder();

// Ensure migrations table exists
if (!$schema->hasTable('migrations')) {
    $schema->create('migrations', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('name')->unique();
        $table->timestamp('ran_at')->useCurrent();
    });
}

$migrationsDir = __DIR__ . '/../database/migrations';
$files = glob($migrationsDir . '/*.php');
// Sort files by name to ensure order
sort($files);

foreach ($files as $file) {
    $migration = require $file;
    if (!is_array($migration) || !isset($migration['name'], $migration['up'])) {
        echo "Skipping invalid migration: $file\n";
        continue;
    }

    $name = $migration['name'];
    $alreadyRan = Capsule::table('migrations')->where('name', $name)->exists();
    if ($alreadyRan) {
        echo "Already ran: $name\n";
        continue;
    }

    echo "Running: $name\n";
    $up = $migration['up'];
    $up($capsule);

    Capsule::table('migrations')->insert(['name' => $name]);
    echo "Done: $name\n";
}

echo "All migrations processed.\n";
