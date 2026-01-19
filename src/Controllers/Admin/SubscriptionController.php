<?php

namespace App\Controllers\Admin;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\Subscription;
use Carbon\Carbon;
use Slim\Psr7\Response;
use Valitron\Validator;

class SubscriptionController extends AppController
{
    public function index($request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = Shop::with(['subscription.package', 'domains']);

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/subscriptions',
            'sortMap' => ['name' => 'name'],
            'filters' => [
                'type' => function ($q, $value) {
                    $q->whereHas('subscription', function ($sub) use ($value) {
                        $sub->where('type', $value);
                    });
                },
            ],
        ]);

        return $this->render($response, 'subscriptions/list.twig', [
            'pager' => $pager,
        ]);
    }

    public function show($request, Response $response): Response
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::with(['domains'])->find($shopId);

        if (!$shop) {
            return $response->withStatus(404);
        }

        $params = $request->getQueryParams();
        $error = $this->flashGet('error', '');
        if ($error === '') {
            $error = $params['error'] ?? '';
        }
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);

        return $this->renderSubscriptionDetail($response, $shop, $data, $errors, $error);
    }

    public function assign($request, Response $response): Response
    {
        $shop = $this->getShopOr404($request, $response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = $request->getParsedBody();
        $validator = new Validator($data);
        $validator->rule('required', 'package_id')->message('Select a valid package.');
        $validator->rule('numeric', 'package_id')->message('Select a valid package.');
        $validator->rule('min', 'package_id', 1)->message('Select a valid package.');
        $validator->rule('required', 'period_months')->message('Select a valid billing period.');
        $validator->rule('in', 'period_months', [1, 3, 6, 12])->message('Select a valid billing period.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $package = $this->getPackage((int)($data['package_id'] ?? 0));
        if ($package === null) {
            $errors['package_id'] = 'Select a valid package.';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/subscriptions/' . $shop->id);
        }

        $startsAt = Carbon::now();
        $expiresAt = $startsAt->copy()->addMonths((int)($data['period_months'] ?? 0));

        $subscription = Subscription::updateOrCreate(
            ['shop_id' => $shop->id],
            [
                'package_id' => $package->id,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'next_renewal_at' => $expiresAt,
                'type' => 'package',
                'renewal_mode' => 'manual',
                'billing_period_months' => (int)($data['period_months'] ?? 0),
            ]
        );

        $this->flashSet('success', 'Subscription assigned successfully.');

        return $this->redirect($response, '/admin/subscriptions/' . $subscription->shop_id);
    }

    public function change($request, Response $response): Response
    {
        $shop = $this->getShopOr404($request, $response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $data = $request->getParsedBody();
        $subscriptionData = $data['subscription'] ?? [];
        $paymentData = $data['payment'] ?? [];
        $type = $subscriptionData['type'] ?? 'package';
        $package = $this->getPackage((int)($subscriptionData['package_id'] ?? 0));
        $validator = new Validator($data);
        $validator->rule('required', 'subscription.type')->message('Subscription type is required.');
        $validator->rule('in', 'subscription.type', ['trial', 'package'])->message('Subscription type is required.');
        if ($type === 'trial') {
            $validator->rule('required', 'subscription.trial_days')->message('Select a valid trial duration.');
            $validator->rule('in', 'subscription.trial_days', [7, 10, 15])->message('Select a valid trial duration.');
        } else {
            $validator->rule('required', 'subscription.package_id')->message('Select a valid package.');
            $validator->rule('numeric', 'subscription.package_id')->message('Select a valid package.');
            $validator->rule('min', 'subscription.package_id', 1)->message('Select a valid package.');
            $validator->rule('required', 'subscription.period_months')->message('Select a valid billing period.');
            $validator->rule('in', 'subscription.period_months', [1, 3, 6, 12])->message('Select a valid billing period.');
            $validator->rule('required', 'payment.method')->message('Payment method is required.');
            $validator->rule('required', 'payment.reference')->message('Payment reference is required.');
            $validator->rule('required', 'payment.amount')->message('Amount is required.');
            $validator->rule('numeric', 'payment.amount')->message('Amount is required.');
            $validator->rule('min', 'payment.amount', 0.01)->message('Amount is required.');
        }
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if ($type === 'trial') {
            if (!empty($errors)) {
                $this->flashSet('errors', $errors);
                $this->flashSet('old', $data);
                return $this->redirect($response, '/admin/subscriptions/' . $shop->id);
            }

            $current = Subscription::where('shop_id', $shop->id)
                ->orderBy('expires_at', 'desc')
                ->first();
            $baseStart = $current && $current->expires_at && Carbon::parse($current->expires_at)->isFuture()
                ? Carbon::parse($current->expires_at)
                : Carbon::now();
            $startsAt = $baseStart;
            $expiresAt = $baseStart->copy()->addDays((int)($subscriptionData['trial_days'] ?? 0));
            $nextRenewalAt = $expiresAt->copy()->addDays((int)($subscriptionData['trial_days'] ?? 0));

            Subscription::create([
                'shop_id' => $shop->id,
                'package_id' => null,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'next_renewal_at' => $nextRenewalAt,
                'type' => 'trial',
                'renewal_mode' => 'manual',
                'trial_days' => (int)($subscriptionData['trial_days'] ?? 0),
                'billing_period_months' => null,
            ]);

            $this->flashSet('success', 'Trial subscription added successfully.');

            return $this->redirect($response, '/admin/subscriptions/' . $shop->id);
        }

        if (!$package) {
            $errors['subscription.package_id'] = 'Select a valid package.';
        }
        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/subscriptions/' . $shop->id);
        }

        $startsAt = Carbon::now();
        $expiresAt = $startsAt->copy()->addMonths((int)($subscriptionData['period_months'] ?? 0));
        $nextRenewalAt = $expiresAt->copy()->addMonths((int)($subscriptionData['period_months'] ?? 0));

        $subscription = Subscription::create([
            'shop_id' => $shop->id,
            'package_id' => $package->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'next_renewal_at' => $nextRenewalAt,
            'type' => 'package',
            'renewal_mode' => 'manual',
            'billing_period_months' => (int)($subscriptionData['period_months'] ?? 0),
        ]);

        $this->recordPaymentFromRequest($shop->id, $subscription, $paymentData);

        $this->flashSet('success', 'Subscription updated successfully.');

        return $this->redirect($response, '/admin/subscriptions/' . $subscription->shop_id);
    }

    public function lock($request, Response $response): Response
    {
        $shop = $this->getShopOr404($request, $response);
        if ($shop instanceof Response) {
            return $shop;
        }

        $shop->status = 'inactive';
        $shop->save();

        if ($shop->subscription) {
            $now = Carbon::now();
            $shop->subscription->next_renewal_at = $now;
            $shop->subscription->expires_at = $now;
            $shop->subscription->save();
        }

        $this->flashSet('success', 'Shop locked successfully.');

        return $this->redirect($response, '/admin/subscriptions/' . $shop->id);
    }

    private function getShopOr404($request, Response $response)
    {
        $shopId = (int)$request->getAttribute('id');
        $shop = Shop::with(['domains'])->find($shopId);
        if (!$shop) {
            return $response->withStatus(404);
        }
        return $shop;
    }

    private function getPackage(int $id): ?Package
    {
        if ($id <= 0) {
            return null;
        }
        return Package::find($id);
    }

    private function recordPaymentFromRequest(int $shopId, Subscription $subscription, array $data): void
    {
        $amount = (float)($data['amount'] ?? 0);
        $method = $data['method'] ?? '';
        $reference = (string)($data['reference'] ?? '');

        if ($amount <= 0 || $method === '') {
            return;
        }

        $paymentId = $reference !== '' ? $reference : ('manual_' . uniqid());
        $orderId = 'order_' . uniqid();

        Payment::create([
            'shop_id' => $shopId,
            'subscription_id' => $subscription->id,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => $subscription->currency ?? 'INR',
            'status' => 'captured',
            'method' => $method,
            'paid_at' => Carbon::now(),
            'notes' => '',
        ]);

        $subscription->payment_method = $method;
        $subscription->price_paid = $amount;
        $subscription->currency = $subscription->currency ?? 'INR';
        $subscription->save();
    }

    private function renderSubscriptionDetail(
        Response $response,
        Shop $shop,
        array $data = [],
        array $errors = [],
        string $error = ''
    ): Response {
        $packages = Package::orderBy('name')->get();
        $currentSubscription = Subscription::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->first();
        $history = Subscription::where('shop_id', $shop->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->render($response, 'subscriptions/detail.twig', [
            'shop' => $shop,
            'packages' => $packages,
            'currentSubscription' => $currentSubscription,
            'history' => $history,
            'error' => $error,
            'data' => $data,
            'errors' => $errors,
        ]);
    }
}
