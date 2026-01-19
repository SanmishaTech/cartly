<?php

namespace App\Controllers;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\ShopDomain;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MailService;
use App\Services\PasswordResetService;
use Cartly\Utilities\AuthLogger;
use Carbon\Carbon;
use Slim\Psr7\Response;
use Valitron\Validator;

class ShopController extends AppController
{
    public function index($request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $sortMap = [
            'name' => 'shop_name',
            'slug' => 'slug',
            'status' => 'status',
            'created' => 'created_at',
        ];

        $query = Shop::with(['domains', 'latestSubscription']);

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/shops',
            'sortMap' => $sortMap,
            'search' => function ($q, $value) {
                $q->where(function ($inner) use ($value) {
                    $inner->where('shop_name', 'like', '%' . $value . '%')
                        ->orWhere('slug', 'like', '%' . $value . '%')
                        ->orWhereHas('domains', function ($domainQuery) use ($value) {
                            $domainQuery->where('domain', 'like', '%' . $value . '%');
                        });
                });
            },
        ]);

        return $this->render($response, 'shops/list.twig', [
            'pager' => $pager,
            'success' => $this->flashGet('success'),
            'error' => $this->flashGet('error'),
        ]);
    }

    public function create($request, Response $response): Response
    {
        $packages = Package::where('active', true)->orderBy('name')->get();
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        return $this->render($response, 'shops/create.twig', [
            'packages' => $packages,
            'trialOptions' => [7, 10, 15],
            'errors' => $errors,
            'data' => $data,
        ]);
    }

    public function store($request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $shopInput = $data['shop'] ?? [];
        $userInput = $data['user'] ?? [];
        $subscriptionInput = $data['subscription'] ?? [];
        $paymentInput = $data['payment'] ?? [];
        $sendPasswordLink = !empty($userInput['send_password_link']);
        $subscriptionType = $subscriptionInput['type'] ?? 'paid';
        $validator = new Validator($data);
        $validator->rule('required', 'shop.shop_name')->message('Shop name is required.');
        $validator->rule('required', 'user.name')->message('Shop admin name is required.');
        $validator->rule('required', 'user.email')->message('Shop admin email is required.');
        $validator->rule('email', 'user.email')->message('Enter a valid email address.');
        if (!$sendPasswordLink) {
            $validator->rule('required', 'user.password')->message('Shop admin password is required.');
            $validator->rule('lengthMin', 'user.password', 6)->message('Password must be at least 6 characters.');
        }

        if ($subscriptionType === 'trial') {
            $validator->rule('required', 'subscription.trial_days')->message('Select a valid trial duration.');
            $validator->rule('in', 'subscription.trial_days', [7, 10, 15])->message('Select a valid trial duration.');
        } else {
            $validator->rule('required', 'subscription.package_id')->message('Subscription package is required.');
            $validator->rule('numeric', 'subscription.package_id')->message('Subscription package is required.');
            $validator->rule('min', 'subscription.package_id', 1)->message('Subscription package is required.');
            $validator->rule('required', 'subscription.period_months')->message('Select a valid billing period.');
            $validator->rule('in', 'subscription.period_months', [1, 3, 6, 12])->message('Select a valid billing period.');
            $validator->rule('required', 'payment.method')->message('Payment method is required.');
            $validator->rule('required', 'payment.amount')->message('Payment amount is required.');
            $validator->rule('numeric', 'payment.amount')->message('Payment amount is required.');
            $validator->rule('min', 'payment.amount', 0.01)->message('Payment amount is required.');
            $validator->rule('required', 'payment.reference')->message('Payment reference is required.');
        }

        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());
        $slugSource = ($shopInput['slug'] ?? '') !== '' ? ($shopInput['slug'] ?? '') : ($shopInput['shop_name'] ?? '');
        $slug = $this->slugify((string)$slugSource);
        if ($slug === '') {
            $errors['shop.slug'] = 'Valid slug is required.';
        }

        if (!$errors && Shop::where('slug', $slug)->exists()) {
            $errors['shop.slug'] = 'Slug already exists. Please choose another.';
        }

        $package = null;
        if ($subscriptionType !== 'trial') {
            if ((int)($subscriptionInput['package_id'] ?? 0) > 0) {
                $package = Package::where('active', true)
                    ->where('id', (int)($subscriptionInput['package_id'] ?? 0))
                    ->first();
            }
            if (!$package) {
                $errors['subscription.package_id'] = 'Selected package is not available.';
            }
        }

        if (($userInput['email'] ?? '') !== '' && !isset($errors['user.email']) && User::where('email', $userInput['email'])->exists()) {
            $errors['user.email'] = 'Email already exists. Use another email.';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/shops/create');
        }

        $shopData = [
            'shop_name' => (string)($shopInput['shop_name'] ?? ''),
            'slug' => $slug,
            'status' => 'active',
            'theme' => 'default',
        ];
        $shop = Shop::create($shopData);

        $appDomain = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?? 'cartly.test';
        $domain = $slug . '.' . $appDomain;

        ShopDomain::create([
            'shop_id' => $shop->id,
            'domain' => $domain,
            'is_primary' => true,
            'is_temp' => true,
            'status' => 'pending',
        ]);

        $userData = [
            'name' => (string)($userInput['name'] ?? ''),
            'email' => (string)($userInput['email'] ?? ''),
            'password' => $sendPasswordLink
                ? $this->generateRandomPassword()
                : (string)($userInput['password'] ?? ''),
            'role' => 'admin',
            'shop_id' => $shop->id,
            'status' => 'active',
        ];
        $adminUser = User::create($userData);

        if ($sendPasswordLink && $adminUser->status === 'active') {
            $this->sendResetLink($request, $adminUser);
            $this->flashSet('success', 'Shop created and password link sent.');
        } else {
            $this->flashSet('success', 'Shop created successfully.');
        }

        $startsAt = Carbon::now();
        $expiresAt = $subscriptionType === 'trial'
            ? $startsAt->copy()->addDays((int)($subscriptionInput['trial_days'] ?? 0))
            : $startsAt->copy()->addMonths((int)($subscriptionInput['period_months'] ?? 0));
        $nextRenewalAt = $subscriptionType === 'trial'
            ? $expiresAt->copy()->addDays((int)($subscriptionInput['trial_days'] ?? 0))
            : $expiresAt->copy()->addMonths((int)($subscriptionInput['period_months'] ?? 0));

        $subscriptionData = [
            'shop_id' => $shop->id,
            'package_id' => $package?->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'next_renewal_at' => $nextRenewalAt,
            'trial_days' => $subscriptionType === 'trial' ? (int)($subscriptionInput['trial_days'] ?? 0) : null,
            'status' => $subscriptionType === 'trial' ? 'trial' : 'active',
            'renewal_mode' => 'manual',
            'payment_method' => $subscriptionType === 'trial' ? null : (string)($paymentInput['method'] ?? ''),
            'price_paid' => $subscriptionType === 'trial' ? null : (float)($paymentInput['amount'] ?? 0),
            'currency' => 'INR',
            'billing_period_months' => $subscriptionType === 'trial' ? null : (int)($subscriptionInput['period_months'] ?? 0),
        ];
        $subscription = Subscription::create($subscriptionData);

        if ($subscriptionType !== 'trial') {
            $paymentReference = (string)($paymentInput['reference'] ?? '');
            $paymentId = $paymentReference !== '' ? $paymentReference : ('manual_' . uniqid());
            $orderId = 'order_' . uniqid();

            $paymentData = [
                'shop_id' => $shop->id,
                'subscription_id' => $subscription->id,
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'amount' => (float)($paymentInput['amount'] ?? 0),
                'currency' => 'INR',
                'status' => 'captured',
                'method' => (string)($paymentInput['method'] ?? ''),
                'paid_at' => Carbon::now(),
                'notes' => '',
            ];
            Payment::create($paymentData);
        }

        return $this->redirect($response, '/admin/shops');
    }

    public function edit($request, Response $response): Response
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::with('domains')->find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        $adminUser = User::where('shop_id', $shopId)
            ->where('role', 'admin')
            ->first();

        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);

        return $this->render($response, 'shops/edit.twig', [
            'shop' => $shop,
            'adminUser' => $adminUser,
            'errors' => $errors,
            'data' => $data,
        ]);
    }

    public function update($request, Response $response): Response
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $userInput = $data['user'] ?? [];
        $shopName = (string)($data['shop_name'] ?? '');
        $slugInput = (string)($data['slug'] ?? '');
        $status = $data['status'] ?? 'active';
        $validator = new Validator($data);
        $validator->rule('required', 'shop_name')->message('Shop name is required.');
        $validator->rule('required', 'status')->message('Invalid status.');
        $validator->rule('in', 'status', ['active', 'inactive'])->message('Invalid status.');
        $validator->rule('required', 'user.name')->message('Shop admin name is required.');
        $validator->rule('required', 'user.email')->message('Shop admin email is required.');
        $validator->rule('email', 'user.email')->message('Enter a valid email address.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $slug = $slugInput !== '' ? $this->slugify($slugInput) : $this->slugify($shopName);
        if ($slug === '') {
            $errors['slug'] = 'Valid slug is required.';
        }

        if (!$errors && Shop::where('slug', $slug)->where('id', '!=', $shop->id)->exists()) {
            $errors['slug'] = 'Slug already exists. Please choose another.';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $errors['status'] = 'Invalid status.';
        }

        $adminUser = User::where('shop_id', $shopId)
            ->where('role', 'admin')
            ->first();
        if (!$adminUser) {
            $errors['user.email'] = 'Shop admin account not found.';
        }

        $adminEmail = (string)($userInput['email'] ?? '');
        if ($adminEmail !== '' && !isset($errors['user.email'])) {
            $duplicateEmail = User::where('email', $adminEmail)
                ->where('id', '!=', $adminUser?->id)
                ->exists();
            if ($duplicateEmail) {
                $errors['user.email'] = 'Email already exists. Use another email.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/shops/' . $shop->id . '/edit');
        }

        $shop->update([
            'shop_name' => $shopName,
            'slug' => $slug,
            'status' => $status,
        ]);

        $adminUser->update([
            'name' => (string)($userInput['name'] ?? ''),
            'email' => $adminEmail,
        ]);

        $this->flashSet('success', 'Shop updated successfully.');

        return $this->redirect($response, '/admin/shops');
    }

    public function delete($request, Response $response): Response
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        User::where('shop_id', $shopId)->delete();
        Payment::where('shop_id', $shopId)->delete();
        Subscription::where('shop_id', $shopId)->delete();
        ShopDomain::where('shop_id', $shopId)->delete();
        $shop->delete();

        $this->flashSet('success', 'Shop deleted successfully.');

        return $this->redirect($response, '/admin/shops');
    }

    public function sendSetPassword($request, Response $response): Response
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }

        $adminUser = User::where('shop_id', $shopId)
            ->where('role', 'admin')
            ->first();

        if (!$adminUser) {
            $this->flashSet('error', 'No shop admin found for this shop.');
            return $this->redirect($response, '/admin/shops');
        }

        if ($adminUser->status !== 'active') {
            $this->flashSet('error', 'Shop admin must be active to send a password link.');
            return $this->redirect($response, '/admin/shops');
        }

        $sent = $this->sendResetLink($request, $adminUser);
        if ($sent) {
            $this->flashSet('success', 'Password link sent to shop admin.');
        } else {
            $this->flashSet('error', 'Failed to send password link. Check SMTP settings.');
        }

        return $this->redirect($response, '/admin/shops');
    }

    private function slugify(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string)$value, '-');
    }

    private function generateRandomPassword(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function sendResetLink($request, User $user): bool
    {
        $resetService = new PasswordResetService();
        $token = $resetService->createForUser($user);
        $resetUrl = $this->buildResetUrl($request, $token);

        $mailService = new MailService();
        $subject = 'Set your Cartly password';
        $htmlBody = $this->buildResetEmailHtml($user->name, $resetUrl);
        $textBody = $this->buildResetEmailText($user->name, $resetUrl);
        $sent = $mailService->send($user->email, $user->name, $subject, $htmlBody, $textBody);

        if ($sent) {
            $logger = new AuthLogger();
            $logger->logPasswordResetRequested(
                $user->email,
                $request->getServerParams()['REMOTE_ADDR'] ?? 'Unknown'
            );
        }

        return $sent;
    }

    private function buildResetUrl($request, string $token): string
    {
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL');
        if ($appUrl) {
            return rtrim($appUrl, '/') . '/admin/reset-password?token=' . urlencode($token);
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $base = $scheme . '://' . $host;
        if ($port && !in_array($port, [80, 443], true)) {
            $base .= ':' . $port;
        }

        return $base . '/admin/reset-password?token=' . urlencode($token);
    }

    private function buildResetEmailHtml(string $name, string $resetUrl): string
    {
        $safeName = htmlspecialchars($name !== '' ? $name : 'there', ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');

        return '<p>Hi ' . $safeName . ',</p>'
            . '<p>Use the link below to set your Cartly password.</p>'
            . '<p><a href="' . $safeUrl . '">Set your password</a></p>'
            . '<p>If you did not request this, you can ignore this email.</p>';
    }

    private function buildResetEmailText(string $name, string $resetUrl): string
    {
        $greetingName = $name !== '' ? $name : 'there';

        return "Hi {$greetingName},\n\n"
            . "Use the link below to set your Cartly password:\n"
            . "{$resetUrl}\n\n"
            . "If you did not request this, you can ignore this email.\n";
    }

}
