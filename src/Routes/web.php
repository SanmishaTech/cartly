<?php

use Slim\App;
use Slim\Views\Twig;
use App\Controllers\HomeController;
use App\Controllers\StorefrontController;
use App\Controllers\PageController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\PackageController;
use App\Controllers\Admin\ShopController;
use App\Controllers\Admin\SubscriptionController;
use App\Controllers\Admin\SudoController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\SetupController;
use App\Controllers\Admin\PageController as AdminPageController;
use App\Controllers\Admin\MenuController;
use App\Controllers\MediaController;
use App\Controllers\ThemeAssetController;
use App\Middleware\RequirePermissionMiddleware;
use App\Middleware\TrimInputMiddleware;
use App\Services\AuthorizationService;

return function (App $app, Twig $twig) {
    $container = $app->getContainer();
    $homeController = $container->get(HomeController::class);
    $mediaController = $container->get(MediaController::class);
    $themeAssetController = $container->get(ThemeAssetController::class);
    $storefrontController = $container->get(StorefrontController::class);
    $pageController = $container->get(PageController::class);

    // Public routes
    $app->get('/', [$homeController, 'index']);
    $app->get('/media/{path:.+}', [$mediaController, 'show']);
    $app->get('/assets/themes/{theme}/{path:.+}', [$themeAssetController, 'show']);
    $app->get('/products', [$storefrontController, 'products']);
    $app->get('/products/{slug}', [$storefrontController, 'productDetail']);
    $app->get('/categories', [$storefrontController, 'categories']);
    $app->get('/categories/{slug}', [$storefrontController, 'categoryDetail']);
    $app->get('/cart', [$storefrontController, 'cart']);
    $app->get('/checkout', [$storefrontController, 'checkout']);
    $app->get('/account', [$storefrontController, 'account']);
    $app->get('/login', [$storefrontController, 'loginForm']);
    $app->post('/login', [$storefrontController, 'login'])->add(new TrimInputMiddleware());
    $app->get('/register', [$storefrontController, 'registerForm']);
    $app->post('/register', [$storefrontController, 'register'])->add(new TrimInputMiddleware());
    $app->get('/forgot-password', [$storefrontController, 'forgotPasswordForm']);

    // Customer OAuth (Google / Facebook) — requires shop context (storefront host)
    $app->get('/auth/google', [$storefrontController, 'redirectToGoogle']);
    $app->get('/auth/google/callback', [$storefrontController, 'googleCallback']);
    $app->get('/auth/facebook', [$storefrontController, 'redirectToFacebook']);
    $app->get('/auth/facebook/callback', [$storefrontController, 'facebookCallback']);

    // Auth routes
    $authController = $container->get(AuthController::class);
    
    // Admin/Root Login (single entrypoint: /admin/login)
    $app->get('/admin/login', [$authController, 'adminLoginForm']);
    $app->post('/admin/login', [$authController, 'adminLogin'])->add(new TrimInputMiddleware());

    // Forgot password and reset
    $app->get('/admin/forgot-password', [$authController, 'adminForgotPasswordForm']);
    $app->post('/admin/forgot-password', [$authController, 'adminForgotPassword'])->add(new TrimInputMiddleware());
    $app->get('/admin/reset-password', [$authController, 'adminResetPasswordForm']);
    $app->post('/admin/reset-password', [$authController, 'adminResetPassword'])->add(new TrimInputMiddleware());

    $app->get('/admin', function($request, $response) {
        return $response->withStatus(302)->withHeader('Location', '/admin/login');
    });
    $app->get('/admin/settings', function ($request, $response) {
        return $response->withStatus(302)->withHeader('Location', '/admin/setup/basic');
    });
    // Logout
    $app->post('/logout', [$authController, 'logout'])->add(new TrimInputMiddleware());

    // Admin routes (protected)
    $dashboardController = $container->get(DashboardController::class);
    $packageController = $container->get(PackageController::class);
    $shopController = $container->get(ShopController::class);
    $subscriptionController = $container->get(SubscriptionController::class);
    $userController = $container->get(UserController::class);
    $sudoController = $container->get(SudoController::class);
    $setupController = $container->get(SetupController::class);
    $adminPageController = $container->get(AdminPageController::class);
    $menuController = $container->get(MenuController::class);
    $authorization = new AuthorizationService();
    
    // Admin dashboard (protected)
    $adminGroup = $app->group('/admin', function ($group) use ($dashboardController) {
        $group->get('/dashboard', [$dashboardController, 'index']);
    });
    $adminGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_DASHBOARD_ACCESS
        )
    );

    // Packages management
    $packagesGroup = $app->group('/admin/packages', function ($packages) use ($packageController) {
        $packages->get('', [$packageController, 'index']);
        $packages->get('/create', [$packageController, 'create']);
        $packages->post('/store', [$packageController, 'store'])->add(new TrimInputMiddleware());
        $packages->get('/{id}/edit', [$packageController, 'edit']);
        $packages->post('/{id}/update', [$packageController, 'update'])->add(new TrimInputMiddleware());
        $packages->post('/{id}/delete', [$packageController, 'delete'])->add(new TrimInputMiddleware());
    });
    $packagesGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_PACKAGES_MANAGE
        )
    );

    // Shops management
    $shopsGroup = $app->group('/admin/shops', function ($shops) use ($shopController) {
        $shops->get('', [$shopController, 'index']);
        $shops->get('/create', [$shopController, 'create']);
        $shops->post('/store', [$shopController, 'store'])->add(new TrimInputMiddleware());
        $shops->get('/{id}/edit', [$shopController, 'edit']);
        $shops->post('/{id}/update', [$shopController, 'update'])->add(new TrimInputMiddleware());
        $shops->post('/{id}/delete', [$shopController, 'delete'])->add(new TrimInputMiddleware());
        $shops->post('/{id}/send-password-link', [$shopController, 'sendSetPassword'])->add(new TrimInputMiddleware());
    });
    $shopsGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SHOPS_MANAGE
        )
    );

    // Subscriptions management
    $subscriptionsGroup = $app->group('/admin/subscriptions', function ($subscriptions) use ($subscriptionController) {
        $subscriptions->get('', [$subscriptionController, 'index']);
        $subscriptions->get('/{id}', [$subscriptionController, 'show']);
        $subscriptions->post('/{id}/assign', [$subscriptionController, 'assign'])->add(new TrimInputMiddleware());
        $subscriptions->post('/{id}/change', [$subscriptionController, 'change'])->add(new TrimInputMiddleware());
        $subscriptions->post('/{id}/lock', [$subscriptionController, 'lock'])->add(new TrimInputMiddleware());
    });
    $subscriptionsGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SUBSCRIPTIONS_MANAGE
        )
    );

    // Users management
    $usersGroup = $app->group('/admin/users', function ($users) use ($userController) {
        $users->get('', [$userController, 'index']);
        $users->get('/create', [$userController, 'create']);
        $users->post('/store', [$userController, 'store'])->add(new TrimInputMiddleware());
        $users->get('/{id}/edit', [$userController, 'edit']);
        $users->post('/{id}/update', [$userController, 'update'])->add(new TrimInputMiddleware());
    });
    $usersGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_USERS_MANAGE
        )
    );

    // Shop admin setup
    $setupGroup = $app->group('/admin/setup', function ($setup) use ($setupController) {
        $setup->get('/basic', [$setupController, 'basic']);
        $setup->post('/basic', [$setupController, 'updateBasic'])->add(new TrimInputMiddleware());
        $setup->get('/seo', [$setupController, 'seo']);
        $setup->post('/seo', [$setupController, 'updateSeo'])->add(new TrimInputMiddleware());
        $setup->get('/hero', [$setupController, 'hero']);
        $setup->post('/hero', [$setupController, 'updateHero'])->add(new TrimInputMiddleware());
        $setup->get('/home', [$setupController, 'home']);
        $setup->post('/home', [$setupController, 'updateHome'])->add(new TrimInputMiddleware());
        $setup->get('/footer', [$setupController, 'footer']);
        $setup->post('/footer', [$setupController, 'updateFooter'])->add(new TrimInputMiddleware());
        $setup->get('/themes', [$setupController, 'themes']);
        $setup->post('/themes', [$setupController, 'updateThemes'])->add(new TrimInputMiddleware());
        $setup->get('/payments', [$setupController, 'payments']);
        $setup->get('/delivery', [$setupController, 'delivery']);
        $setup->get('/discounts', [$setupController, 'discounts']);
        $setup->get('/customer-auth', [$setupController, 'customerAuth']);
        $setup->post('/customer-auth', [$setupController, 'updateCustomerAuth'])->add(new TrimInputMiddleware());
        $setup->get('/email', [$setupController, 'email']);
        $setup->post('/email', [$setupController, 'updateEmail'])->add(new TrimInputMiddleware());
        $setup->post('/email/verify-domain', [$setupController, 'verifyDomain'])->add(new TrimInputMiddleware());
        $setup->post('/email/test', [$setupController, 'sendTestEmail'])->add(new TrimInputMiddleware());
    });
    $setupGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SETUP_ACCESS
        )
    );

    // Pages management
    $pagesGroup = $app->group('/admin/pages', function ($pages) use ($adminPageController) {
        $pages->get('', [$adminPageController, 'index']);
        $pages->get('/create', [$adminPageController, 'create']);
        $pages->post('/store', [$adminPageController, 'store'])->add(new TrimInputMiddleware());
        $pages->get('/{id}/edit', [$adminPageController, 'edit']);
        $pages->post('/{id}/update', [$adminPageController, 'update'])->add(new TrimInputMiddleware());
        $pages->post('/{id}/publish', [$adminPageController, 'publish'])->add(new TrimInputMiddleware());
        $pages->post('/{id}/unpublish', [$adminPageController, 'unpublish'])->add(new TrimInputMiddleware());
    });
    $pagesGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SETUP_ACCESS
        )
    );

    // Menus management
    $menusGroup = $app->group('/admin/menus', function ($menus) use ($menuController) {
        $menus->get('', [$menuController, 'edit']);
        $menus->post('', [$menuController, 'update'])->add(new TrimInputMiddleware());
    });
    $menusGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SETUP_ACCESS
        )
    );

    // Sudo login (helpdesk)
    $sudoGroup = $app->group('/admin/sudo', function ($sudo) use ($sudoController) {
        $sudo->get('', [$sudoController, 'index']);
        $sudo->post('/{id}/login', [$sudoController, 'login'])->add(new TrimInputMiddleware());
    });
    $sudoGroup->add(
        new RequirePermissionMiddleware(
            $authorization,
            AuthorizationService::PERMISSION_SUPPORT_SUDO
        )
    );

    // Storefront pages (keep last to avoid collisions)
    $app->get('/{slug}', [$pageController, 'show']);
};


