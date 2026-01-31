<?php

namespace App\Controllers;

use App\Models\Shop;
use App\Models\ShopCustomer;
use App\Models\ShopMetadata;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserOAuthAccount;
use App\Services\FlashService;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use Valitron\Validator;

class StorefrontController
{
    public function __construct(
        protected Twig $view
    ) {}

    public function products($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $products = $this->placeholderProducts();
        $categories = $this->placeholderCategories();

        return $this->view->render($response, 'pages/products.twig', [
            'shop' => $request->getAttribute('shop'),
            'products' => $products,
            'categories' => $categories,
        ]);
    }

    public function productDetail($request, Response $response, array $args): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $slug = $args['slug'] ?? 'product';
        $product = $this->placeholderProductFromSlug($slug);

        return $this->view->render($response, 'pages/product.twig', [
            'shop' => $request->getAttribute('shop'),
            'product' => $product,
        ]);
    }

    public function categories($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/categories.twig', [
            'shop' => $request->getAttribute('shop'),
            'categories' => $this->placeholderCategories(),
        ]);
    }

    public function categoryDetail($request, Response $response, array $args): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $slug = $args['slug'] ?? 'category';
        $category = $this->placeholderCategoryFromSlug($slug);

        return $this->view->render($response, 'pages/category.twig', [
            'shop' => $request->getAttribute('shop'),
            'category' => $category,
            'products' => $this->placeholderProducts(),
        ]);
    }

    public function cart($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/cart.twig', [
            'shop' => $request->getAttribute('shop'),
            'cart_items' => $this->placeholderCartItems(),
        ]);
    }

    public function checkout($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/checkout.twig', [
            'shop' => $request->getAttribute('shop'),
            'cart_items' => $this->placeholderCartItems(),
        ]);
    }

    public function account($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        return $this->view->render($response, 'pages/account.twig', [
            'shop' => $request->getAttribute('shop'),
            'orders' => $this->placeholderOrders(),
        ]);
    }

    public function loginForm($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $shop = $request->getAttribute('shop');
        $oauthConfig = $this->getOauthConfigForShop($shop);
        $errors = FlashService::get('errors', []);
        $old = FlashService::get('old', []);

        return $this->view->render($response, 'pages/login.twig', [
            'shop' => $shop,
            'oauth_config' => $oauthConfig,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function login($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $data = $request->getParsedBody() ?? [];
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');

        $validator = new Validator($data);
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'password')->message('Password is required.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if (!empty($errors)) {
            FlashService::set('errors', $errors);
            FlashService::set('old', ['email' => $email]);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }

        $user = User::where('email', $email)->first();
        if (!$user || !$user->verifyPassword($password)) {
            FlashService::set('errors', ['email' => 'Invalid email or password.']);
            FlashService::set('old', ['email' => $email]);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }

        if ($user->status !== 'active') {
            FlashService::set('errors', ['email' => 'Your account is not active.']);
            FlashService::set('old', ['email' => $email]);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->email;

        $this->recordShopCustomer($request, $user);

        FlashService::set('success', 'You are now signed in.');
        return $response->withStatus(302)->withHeader('Location', '/account');
    }

    public function registerForm($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $shop = $request->getAttribute('shop');
        $oauthConfig = $this->getOauthConfigForShop($shop);
        $errors = FlashService::get('errors', []);
        $old = FlashService::get('old', []);

        return $this->view->render($response, 'pages/register.twig', [
            'shop' => $shop,
            'oauth_config' => $oauthConfig,
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    public function redirectToGoogle($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }
        $shop = $request->getAttribute('shop');
        $config = $this->getOauthConfigForShop($shop);
        if (empty($config['google']['enabled']) || empty($config['google']['client_id'])) {
            FlashService::set('errors', ['oauth' => 'Google login is not configured for this store.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $callbackUrl = $this->oauthCallbackUrl($request, '/auth/google/callback');
        $state = bin2hex(random_bytes(16));
        $this->oauthSetState($state);
        $params = http_build_query([
            'client_id' => $config['google']['client_id'],
            'redirect_uri' => $callbackUrl,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]);
        return $response->withStatus(302)->withHeader('Location', 'https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    public function googleCallback($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }
        $query = $request->getQueryParams();
        $code = $query['code'] ?? '';
        $state = $query['state'] ?? '';
        if ($code === '' || !$this->oauthValidateState($state)) {
            FlashService::set('errors', ['oauth' => 'Invalid or expired login request. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $shop = $request->getAttribute('shop');
        $config = $this->getOauthConfigForShop($shop);
        $callbackUrl = $this->oauthCallbackUrl($request, '/auth/google/callback');
        $tokenBody = $this->httpPost('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $config['google']['client_id'],
            'client_secret' => $config['google']['client_secret'] ?? '',
            'redirect_uri' => $callbackUrl,
            'grant_type' => 'authorization_code',
        ]);
        $tokenData = json_decode($tokenBody, true);
        $accessToken = $tokenData['access_token'] ?? null;
        if (!$accessToken) {
            FlashService::set('errors', ['oauth' => 'Could not sign in with Google. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $userInfoBody = $this->httpGet('https://www.googleapis.com/oauth2/v2/userinfo', $accessToken);
        $userInfo = json_decode($userInfoBody, true);
        $email = isset($userInfo['email']) ? trim((string)$userInfo['email']) : '';
        $providerUserId = (string)($userInfo['id'] ?? '');
        $name = trim((string)($userInfo['name'] ?? $email));
        if ($email === '') {
            FlashService::set('errors', ['oauth' => 'Google did not provide an email.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $user = $this->findOrCreateUserFromOAuth('google', $providerUserId, $email, $name);
        if (!$user) {
            FlashService::set('errors', ['oauth' => 'Could not sign you in. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $this->storefrontLogin($user, $name);
        $this->recordShopCustomer($request, $user);
        FlashService::set('success', 'You are now signed in.');
        return $response->withStatus(302)->withHeader('Location', '/account');
    }

    public function redirectToFacebook($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }
        $shop = $request->getAttribute('shop');
        $config = $this->getOauthConfigForShop($shop);
        if (empty($config['facebook']['enabled']) || empty($config['facebook']['app_id'])) {
            FlashService::set('errors', ['oauth' => 'Facebook login is not configured for this store.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $callbackUrl = $this->oauthCallbackUrl($request, '/auth/facebook/callback');
        $state = bin2hex(random_bytes(16));
        $this->oauthSetState($state);
        $params = http_build_query([
            'client_id' => $config['facebook']['app_id'],
            'redirect_uri' => $callbackUrl,
            'state' => $state,
            'scope' => 'email,public_profile',
        ]);
        return $response->withStatus(302)->withHeader('Location', 'https://www.facebook.com/v18.0/dialog/oauth?' . $params);
    }

    public function facebookCallback($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }
        $query = $request->getQueryParams();
        $code = $query['code'] ?? '';
        $state = $query['state'] ?? '';
        if ($code === '' || !$this->oauthValidateState($state)) {
            FlashService::set('errors', ['oauth' => 'Invalid or expired login request. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $shop = $request->getAttribute('shop');
        $config = $this->getOauthConfigForShop($shop);
        $callbackUrl = $this->oauthCallbackUrl($request, '/auth/facebook/callback');
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query([
            'client_id' => $config['facebook']['app_id'],
            'client_secret' => $config['facebook']['app_secret'] ?? '',
            'redirect_uri' => $callbackUrl,
            'code' => $code,
        ]);
        $tokenBody = $this->httpGet($tokenUrl);
        $tokenData = json_decode($tokenBody, true);
        $accessToken = $tokenData['access_token'] ?? null;
        if (!$accessToken) {
            FlashService::set('errors', ['oauth' => 'Could not sign in with Facebook. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $userInfoUrl = 'https://graph.facebook.com/me?fields=id,email,name&access_token=' . urlencode($accessToken);
        $userInfoBody = $this->httpGet($userInfoUrl);
        $userInfo = json_decode($userInfoBody, true);
        $email = isset($userInfo['email']) ? trim((string)$userInfo['email']) : '';
        $providerUserId = (string)($userInfo['id'] ?? '');
        $name = trim((string)($userInfo['name'] ?? $email));
        if ($email === '') {
            FlashService::set('errors', ['oauth' => 'Facebook did not provide an email. Please allow email permission.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $user = $this->findOrCreateUserFromOAuth('facebook', $providerUserId, $email, $name);
        if (!$user) {
            FlashService::set('errors', ['oauth' => 'Could not sign you in. Please try again.']);
            return $response->withStatus(302)->withHeader('Location', '/login');
        }
        $this->storefrontLogin($user, $name);
        $this->recordShopCustomer($request, $user);
        FlashService::set('success', 'You are now signed in.');
        return $response->withStatus(302)->withHeader('Location', '/account');
    }

    public function forgotPasswordForm($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $shop = $request->getAttribute('shop');

        return $this->view->render($response, 'pages/forgot_password.twig', [
            'shop' => $shop,
        ]);
    }

    public function register($request, Response $response): Response
    {
        if ($landing = $this->renderLandingIfNoShop($request, $response)) {
            return $landing;
        }

        $data = $request->getParsedBody() ?? [];
        $email = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $passwordConfirm = (string)($data['password_confirm'] ?? '');

        $validator = new Validator($data);
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'password')->message('Password is required.');
        $validator->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters.');
        $validator->rule('required', 'password_confirm')->message('Please confirm your password.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if ($password !== $passwordConfirm) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        if ($email !== '' && !isset($errors['email']) && User::where('email', $email)->exists()) {
            $errors['email'] = 'An account with this email already exists.';
        }

        if (!empty($errors)) {
            FlashService::set('errors', $errors);
            FlashService::set('old', ['email' => $email]);
            return $response->withStatus(302)->withHeader('Location', '/register');
        }

        $user = User::create([
            'email' => $email,
            'password' => $password,
            'status' => 'active',
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $user->email;

        $this->recordShopCustomer($request, $user);

        FlashService::set('success', 'Account created. You are now signed in.');
        return $response->withStatus(302)->withHeader('Location', '/account');
    }

    private function formatValitronErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $field => $messages) {
            if (is_array($messages) && count($messages) > 0) {
                $flat[$field] = $messages[0];
            }
        }
        return $flat;
    }

    private function getOauthConfigForShop($shop): array
    {
        if (!$shop) {
            return ['google' => ['enabled' => false], 'facebook' => ['enabled' => false]];
        }

        $metadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $raw = $metadata?->oauth_config ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'google' => array_merge(['enabled' => false], $raw['google'] ?? []),
            'facebook' => array_merge(['enabled' => false], $raw['facebook'] ?? []),
        ];
    }

    private function oauthCallbackUrl($request, string $path): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: 'https';
        $host = $uri->getHost();
        return $scheme . '://' . $host . $path;
    }

    private function oauthSetState(string $state): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['oauth_state'] = $state;
    }

    private function oauthValidateState(string $state): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $stored = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state']);
        return $stored !== '' && hash_equals($stored, $state);
    }

    private function httpGet(string $url, ?string $bearerToken = null): string
    {
        $opts = ['http' => ['method' => 'GET', 'ignore_errors' => true]];
        if ($bearerToken !== null) {
            $opts['http']['header'] = 'Authorization: Bearer ' . $bearerToken . "\r\n";
        }
        $ctx = stream_context_create($opts);
        $body = @file_get_contents($url, false, $ctx);
        return $body !== false ? $body : '';
    }

    private function httpPost(string $url, array $formParams): string
    {
        $body = http_build_query($formParams);
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($body) . "\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ];
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);
        return $response !== false ? $response : '';
    }

    private function findOrCreateUserFromOAuth(string $provider, string $providerUserId, string $email, string $name): ?User
    {
        $oauthAccount = UserOAuthAccount::where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();
        if ($oauthAccount && $oauthAccount->user) {
            $user = $oauthAccount->user;
            if ($name !== '' && ($user->name === null || $user->name === '')) {
                $user->update(['name' => $name]);
            }
            return $user->status === 'active' ? $user : null;
        }
        $user = User::where('email', $email)->first();
        if ($user) {
            UserOAuthAccount::firstOrCreate(
                ['provider' => $provider, 'provider_user_id' => $providerUserId],
                ['user_id' => $user->id, 'email' => $email]
            );
            if ($name !== '' && ($user->name === null || $user->name === '')) {
                $user->update(['name' => $name]);
            }
            return $user->status === 'active' ? $user : null;
        }
        $user = new User();
        $user->email = $email;
        $user->name = $name !== '' ? $name : null;
        $user->password = null;
        $user->global_role = null;
        $user->status = 'active';
        $user->save();
        UserOAuthAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email,
        ]);
        return $user;
    }

    /**
     * Log in customer to storefront session.
     * @param string|null $displayName Optional (e.g. from OAuth); falls back to email.
     */
    private function storefrontLogin(User $user, ?string $displayName = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name'] = $displayName !== null && $displayName !== '' ? $displayName : $user->email;
    }

    /**
     * Record shop_customers when a user logs in on a storefront (login, register, OAuth).
     */
    private function recordShopCustomer($request, User $user): void
    {
        $shop = $request->getAttribute('shop');
        if (!$shop instanceof Shop) {
            return;
        }
        $now = Carbon::now();
        $record = ShopCustomer::where('shop_id', $shop->id)->where('user_id', $user->id)->first();
        if ($record) {
            $record->update(['last_seen_at' => $now]);
        } else {
            ShopCustomer::create([
                'shop_id' => $shop->id,
                'user_id' => $user->id,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }
    }

    private function renderLandingIfNoShop($request, Response $response): ?Response
    {
        $shop = $request->getAttribute('shop');
        if ($shop) {
            return null;
        }

        return $this->view->render($response, 'home.twig');
    }

    private function placeholderProducts(): array
    {
        return [
            [
                'name' => 'Classic Cotton T-Shirt',
                'slug' => 'classic-cotton-tshirt',
                'price' => '₹899',
                'badge' => 'New',
            ],
            [
                'name' => 'Wireless Earbuds',
                'slug' => 'wireless-earbuds',
                'price' => '₹2,499',
                'badge' => 'Hot',
            ],
            [
                'name' => 'Minimal Backpack',
                'slug' => 'minimal-backpack',
                'price' => '₹1,799',
                'badge' => 'Limited',
            ],
            [
                'name' => 'Ceramic Coffee Mug',
                'slug' => 'ceramic-coffee-mug',
                'price' => '₹499',
                'badge' => 'Bestseller',
            ],
            [
                'name' => 'Smart Fitness Band',
                'slug' => 'smart-fitness-band',
                'price' => '₹3,199',
                'badge' => 'Trending',
            ],
            [
                'name' => 'Wooden Desk Organizer',
                'slug' => 'wooden-desk-organizer',
                'price' => '₹1,099',
                'badge' => 'Eco',
            ],
            [
                'name' => 'Studio Headphones',
                'slug' => 'studio-headphones',
                'price' => '₹4,699',
                'badge' => 'Top Rated',
            ],
            [
                'name' => 'Scented Candle Set',
                'slug' => 'scented-candle-set',
                'price' => '₹1,299',
                'badge' => 'Gift',
            ],
        ];
    }

    private function placeholderCategories(): array
    {
        return [
            [
                'name' => 'Electronics',
                'slug' => 'electronics',
                'count' => '128 items',
            ],
            [
                'name' => 'Fashion',
                'slug' => 'fashion',
                'count' => '342 items',
            ],
            [
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'count' => '98 items',
            ],
            [
                'name' => 'Beauty',
                'slug' => 'beauty',
                'count' => '56 items',
            ],
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'count' => '74 items',
            ],
            [
                'name' => 'Grocery',
                'slug' => 'grocery',
                'count' => '210 items',
            ],
            [
                'name' => 'Kids',
                'slug' => 'kids',
                'count' => '88 items',
            ],
            [
                'name' => 'Accessories',
                'slug' => 'accessories',
                'count' => '152 items',
            ],
        ];
    }

    private function placeholderCartItems(): array
    {
        return [
            [
                'name' => 'Classic Cotton T-Shirt',
                'price' => '₹899',
                'qty' => 1,
            ],
            [
                'name' => 'Minimal Backpack',
                'price' => '₹1,799',
                'qty' => 1,
            ],
        ];
    }

    private function placeholderOrders(): array
    {
        return [
            [
                'id' => 'ORD-1245',
                'date' => '12 Jan 2026',
                'status' => 'Delivered',
                'total' => '₹3,498',
            ],
            [
                'id' => 'ORD-1189',
                'date' => '02 Jan 2026',
                'status' => 'Processing',
                'total' => '₹1,299',
            ],
        ];
    }

    private function placeholderProductFromSlug(string $slug): array
    {
        $name = ucwords(str_replace('-', ' ', $slug));
        return [
            'name' => $name ?: 'Product',
            'slug' => $slug ?: 'product',
            'price' => '₹2,199',
            'badge' => 'Placeholder',
            'sku' => 'SKU-PLACEHOLDER',
            'stock' => 'In Stock',
        ];
    }

    private function placeholderCategoryFromSlug(string $slug): array
    {
        $name = ucwords(str_replace('-', ' ', $slug));
        return [
            'name' => $name ?: 'Category',
            'slug' => $slug ?: 'category',
            'description' => 'This is a placeholder category description. Replace with your collection story.',
        ];
    }
}
