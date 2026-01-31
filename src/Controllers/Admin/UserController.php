<?php

namespace App\Controllers\Admin;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\AuthorizationService;
use Slim\Psr7\Response;
use Valitron\Validator;

class UserController extends AppController
{
    public function index($request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $sortMap = [
            'email' => 'email',
            'global_role' => 'global_role',
            'status' => 'status',
            'created' => 'created_at',
        ];

        $query = User::query()
            ->with(['shopUsers.shop.domains'])
            ->select('users.*');

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/users',
            'sortMap' => $sortMap,
            'search' => function ($q, $value) {
                $q->where(function ($inner) use ($value) {
                    $inner->where('users.email', 'like', '%' . $value . '%')
                        ->orWhere('users.global_role', 'like', '%' . $value . '%')
                        ->orWhereHas('shopUsers.shop', function ($shopQuery) use ($value) {
                            $shopQuery->where('shop_name', 'like', '%' . $value . '%')
                                ->orWhereHas('domains', function ($domainQuery) use ($value) {
                                    $domainQuery->where('domain', 'like', '%' . $value . '%');
                                });
                        });
                });
            },
        ]);

        return $this->render($response, 'users/list.twig', [
            'pager' => $pager,
        ]);
    }

    public function create($request, Response $response): Response
    {
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);
        $shops = Shop::orderBy('shop_name')->get();

        return $this->render($response, 'users/create.twig', [
            'errors' => $errors,
            'data' => $data,
            'global_roles' => $this->getGlobalRoles(),
            'shop_roles' => $this->getShopRoles(),
            'shops' => $shops,
        ]);
    }

    public function store($request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = (string)($data['email'] ?? '');
        $globalRole = $this->normalizeGlobalRole($data['global_role'] ?? null);
        $status = (string)($data['status'] ?? 'active');
        $password = (string)($data['password'] ?? '');
        $shopId = (int)($data['shop_id'] ?? 0);
        $shopRole = (string)($data['shop_role'] ?? 'owner');

        $validator = new Validator($data);
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'status')->message('Status is required.');
        $validator->rule('in', 'status', ['active', 'inactive'])->message('Invalid status.');
        $validator->rule('required', 'password')->message('Password is required.');
        $validator->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if ($globalRole !== null && !in_array($globalRole, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            $errors['global_role'] = 'Invalid global role.';
        }

        if ($email !== '' && !isset($errors['email'])) {
            if (User::where('email', $email)->exists()) {
                $errors['email'] = 'Email already exists. Use another email.';
            }
        }

        if ($shopId > 0) {
            if (!Shop::where('id', $shopId)->exists()) {
                $errors['shop_id'] = 'Select a valid shop.';
            }
            if (!in_array($shopRole, $this->getShopRoles(), true)) {
                $errors['shop_role'] = 'Invalid shop role.';
            }
        }

        if ($globalRole === null && $shopId <= 0) {
            $errors['shop_id'] = 'Either set a global role or link the user to a shop.';
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/users/create');
        }

        $user = User::create([
            'email' => $email,
            'password' => $password,
            'global_role' => $globalRole,
            'status' => $status,
        ]);

        if ($shopId > 0) {
            ShopUser::create([
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'role' => $shopRole,
            ]);
        }

        $this->flashSet('success', 'User created successfully.');

        return $this->redirect($response, '/admin/users');
    }

    public function edit($request, Response $response): Response
    {
        $userId = (int)$request->getAttribute('id');
        $user = User::with('shopUsers.shop')->find($userId);
        if (!$user) {
            return $response->withStatus(404);
        }

        $primaryMembership = $user->shopUsers->first();
        $shop = $primaryMembership ? $primaryMembership->shop : null;
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);

        return $this->render($response, 'users/edit.twig', [
            'user' => $user,
            'shop' => $shop,
            'primary_membership' => $primaryMembership,
            'global_roles' => $this->getGlobalRoles(),
            'shop_roles' => $this->getShopRoles(),
            'errors' => $errors,
            'data' => $data,
            'shops' => Shop::orderBy('shop_name')->get(),
        ]);
    }

    public function update($request, Response $response): Response
    {
        $userId = (int)$request->getAttribute('id');
        $user = User::find($userId);
        if (!$user) {
            return $response->withStatus(404);
        }

        $data = $request->getParsedBody();
        $email = (string)($data['email'] ?? '');
        $globalRole = $this->normalizeGlobalRole($data['global_role'] ?? null);
        $status = (string)($data['status'] ?? 'active');
        $password = (string)($data['password'] ?? '');
        $shopId = (int)($data['shop_id'] ?? 0);
        $shopRole = (string)($data['shop_role'] ?? 'owner');

        $validator = new Validator($data);
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'status')->message('Status is required.');
        $validator->rule('in', 'status', ['active', 'inactive'])->message('Invalid status.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        if ($globalRole !== null && !in_array($globalRole, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            $errors['global_role'] = 'Invalid global role.';
        }

        if ($email !== '' && !isset($errors['email'])) {
            if (User::where('email', $email)->where('id', '!=', $user->id)->exists()) {
                $errors['email'] = 'Email already exists. Use another email.';
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if ($shopId > 0) {
            if (!Shop::where('id', $shopId)->exists()) {
                $errors['shop_id'] = 'Select a valid shop.';
            }
            if (!in_array($shopRole, $this->getShopRoles(), true)) {
                $errors['shop_role'] = 'Invalid shop role.';
            }
        }

        if ($globalRole === null && $shopId <= 0) {
            $existingShops = $user->shopUsers()->count();
            if ($existingShops === 0) {
                $errors['shop_id'] = 'Either set a global role or link the user to a shop.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/users/' . $user->id . '/edit');
        }

        $user->update([
            'email' => $email,
            'global_role' => $globalRole,
            'status' => $status,
        ]);

        if ($password !== '') {
            $user->password = $password;
            $user->save();
        }

        if ($shopId > 0) {
            $existing = ShopUser::where('user_id', $user->id)->where('shop_id', $shopId)->first();
            if ($existing) {
                $existing->update(['role' => $shopRole]);
            } else {
                ShopUser::create([
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'role' => $shopRole,
                ]);
            }
        }

        $this->flashSet('success', 'User updated successfully.');

        return $this->redirect($response, '/admin/users');
    }

    private function getGlobalRoles(): array
    {
        return [
            '' => 'None (shop only)',
            AuthorizationService::ROLE_ROOT => 'Root',
            AuthorizationService::ROLE_HELPDESK => 'Helpdesk',
        ];
    }

    private function getShopRoles(): array
    {
        return [
            ShopUser::ROLE_OWNER,
            ShopUser::ROLE_ADMIN,
            ShopUser::ROLE_STAFF,
        ];
    }

    private function normalizeGlobalRole($value): ?string
    {
        $v = trim((string)($value ?? ''));
        if ($v === '' || $v === 'none') {
            return null;
        }
        return in_array($v, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true) ? $v : null;
    }
}
