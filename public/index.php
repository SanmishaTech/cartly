<?php

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use App\Middleware\ShopCustomerMiddleware;
use App\Middleware\ShopResolverMiddleware;
use App\Middleware\SubscriptionEnforcerMiddleware;
use App\Middleware\ThemeMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\SeoService;
use App\Services\MenuService;
use App\Services\ThemeResolver;
use App\Services\MailResolver;
use App\Services\MailService;
use App\Services\TransactionalMailService;
use App\Controllers\ThemeAssetController;
use App\Twig\ThemeExtension;
use App\Config\LocalizationConfig;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Initialize timezone and localization
LocalizationConfig::initializeTimezone();

// Base views path for Twig (theme middleware overrides loader per request)
$viewsPath = __DIR__ . '/../src/Views';
$twig = Twig::create($viewsPath, ['cache' => false]);

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

// Set the event dispatcher used by Eloquent models... (optional)
$capsule->setEventDispatcher(new Dispatcher(new Container));

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you plan to use it)
$capsule->bootEloquent();

// Create DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    Twig::class => $twig,
    ThemeResolver::class => function () {
        return new ThemeResolver(__DIR__ . '/../src/Views');
    },
    SeoService::class => function () {
        return new SeoService();
    },
    MenuService::class => function () {
        return new MenuService();
    },
    ThemeAssetController::class => function () {
        return new ThemeAssetController();
    },
    MailResolver::class => function () {
        return new MailResolver();
    },
    MailService::class => function () {
        return new MailService();
    },
    TransactionalMailService::class => function (\Psr\Container\ContainerInterface $c) {
        return new TransactionalMailService(
            $c->get(MailResolver::class),
            $c->get(MailService::class)
        );
    },
]);
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$themeResolver = $container->get(ThemeResolver::class);
$seoService = $container->get(SeoService::class);
$menuService = $container->get(MenuService::class);

// Add extensions to Twig
$twig->getEnvironment()->addExtension(new ThemeExtension($themeResolver));
$twig->getEnvironment()->addExtension(new \App\Twig\LocalizationExtension());

$app->add(TwigMiddleware::create($app, $twig));

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

// NOTE: Middleware in Slim runs in REVERSE order (last added runs first)
// Execution order: CsrfMiddleware -> AuthMiddleware -> ShopResolverMiddleware -> ShopCustomerMiddleware -> SubscriptionEnforcerMiddleware -> ThemeMiddleware -> Routes

// Configure theme system based on context and shop (runs 4th)
$app->add(new ThemeMiddleware($twig, $themeResolver, $seoService, $menuService));
// Compute subscription state (active/grace/expired) (runs 3rd)
$app->add(new SubscriptionEnforcerMiddleware());
// Record shop_customers when logged-in user is seen on storefront (needs shop from ShopResolver)
$app->add(new ShopCustomerMiddleware());
// Resolve shop from Host header (runs 2nd; must run before ShopCustomerMiddleware)
$app->add(new ShopResolverMiddleware());
// Check authentication status (runs 1st)
$app->add(new AuthMiddleware());
// Enforce CSRF on state-changing requests (runs 0th)
$app->add(new CsrfMiddleware($twig));

// Routes
(require __DIR__ . '/../src/Routes/web.php')($app, $twig);

$app->run();

