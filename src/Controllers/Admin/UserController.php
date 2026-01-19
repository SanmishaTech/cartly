<?php

namespace App\Controllers\Admin;

use App\Models\Shop;
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
            'name' => 'name',
            'email' => 'email',
            'role' => 'role',
            'status' => 'status',
            'created' => 'created_at',
        ];

        $query = User::query()
            ->with('shop.domains')
            ->leftJoin('shops', 'users.shop_id', '=', 'shops.id')
            ->select('users.*', 'shops.shop_name as shop_name');

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/users',
            'sortMap' => $sortMap,
            'search' => function ($q, $value) {
                $q->where(function ($inner) use ($value) {
                    $inner->where('users.name', 'like', '%' . $value . '%')
                        ->orWhere('users.email', 'like', '%' . $value . '%')
                        ->orWhere('shops.shop_name', 'like', '%' . $value . '%')
                        ->orWhereHas('shop', function ($shopQuery) use ($value) {
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
            'roles' => $this->getRoles(),
            'shops' => $shops,
        ]);
    }

    public function store($request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $name = (string)($data['name'] ?? '');
        $email = (string)($data['email'] ?? '');
        $role = (string)($data['role'] ?? '');
        $status = (string)($data['status'] ?? '');
        $password = (string)($data['password'] ?? '');
        $shopId = (int)($data['shop_id'] ?? 0);

        $validator = new Validator($data);
        $validator->rule('required', 'name')->message('Name is required.');
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'role')->message('Role is required.');
        $validator->rule('required', 'status')->message('Status is required.');
        $validator->rule('in', 'status', ['active', 'inactive'])->message('Invalid status.');
        $validator->rule('required', 'password')->message('Password is required.');
        $validator->rule('lengthMin', 'password', 6)->message('Password must be at least 6 characters.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $roles = $this->getRoles();
        if ($role !== '' && !in_array($role, $roles, true)) {
            $errors['role'] = 'Invalid role.';
        }

        if ($email !== '' && !isset($errors['email'])) {
            $exists = User::where('email', $email)->exists();
            if ($exists) {
                $errors['email'] = 'Email already exists. Use another email.';
            }
        }

        if ($shopId > 0) {
            $shopExists = Shop::where('id', $shopId)->exists();
            if (!$shopExists) {
                $errors['shop_id'] = 'Select a valid shop.';
            }
        }

        if (!in_array($role, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            if ($shopId <= 0) {
                $errors['shop_id'] = 'Linked shop is required for this role.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/users/create');
        }

        $linkedShopId = $shopId > 0 ? $shopId : null;
        if (in_array($role, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            $linkedShopId = null;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'shop_id' => $linkedShopId,
            'password' => $password,
        ]);

        $this->flashSet('success', 'User created successfully.');

        return $this->redirect($response, '/admin/users');
    }

    public function edit($request, Response $response): Response
    {
        $userId = (int)$request->getAttribute('id');
        $user = User::find($userId);
        if (!$user) {
            return $response->withStatus(404);
        }

        $shop = $user->shop_id ? Shop::find($user->shop_id) : null;
        $errors = $this->flashGet('errors', []);
        $data = $this->flashGet('old', []);

        return $this->render($response, 'users/edit.twig', [
            'user' => $user,
            'shop' => $shop,
            'roles' => $this->getRoles(),
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
        $name = (string)($data['name'] ?? '');
        $email = (string)($data['email'] ?? '');
        $role = (string)($data['role'] ?? '');
        $status = (string)($data['status'] ?? '');
        $password = (string)($data['password'] ?? '');
        $shopId = (int)($data['shop_id'] ?? 0);

        $validator = new Validator($data);
        $validator->rule('required', 'name')->message('Name is required.');
        $validator->rule('required', 'email')->message('Email is required.');
        $validator->rule('email', 'email')->message('Enter a valid email address.');
        $validator->rule('required', 'role')->message('Role is required.');
        $validator->rule('required', 'status')->message('Status is required.');
        $validator->rule('in', 'status', ['active', 'inactive'])->message('Invalid status.');
        $errors = $validator->validate() ? [] : $this->formatValitronErrors($validator->errors());

        $roles = $this->getRoles();
        if ($role !== '' && !in_array($role, $roles, true)) {
            $errors['role'] = 'Invalid role.';
        }

        if ($email !== '' && !isset($errors['email'])) {
            $exists = User::where('email', $email)
                ->where('id', '!=', $user->id)
                ->exists();
            if ($exists) {
                $errors['email'] = 'Email already exists. Use another email.';
            }
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if ($shopId > 0) {
            $shopExists = Shop::where('id', $shopId)->exists();
            if (!$shopExists) {
                $errors['shop_id'] = 'Select a valid shop.';
            }
        }

        if (!in_array($role, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            if ($shopId <= 0) {
                $errors['shop_id'] = 'Linked shop is required for this role.';
            }
        }

        if (!empty($errors)) {
            $this->flashSet('errors', $errors);
            $this->flashSet('old', $data);
            return $this->redirect($response, '/admin/users/' . $user->id . '/edit');
        }

        $linkedShopId = $shopId > 0 ? $shopId : null;
        if (in_array($role, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            $linkedShopId = null;
        }

        $user->update([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status,
            'shop_id' => $linkedShopId,
        ]);

        if ($password !== '') {
            $user->password = $password;
            $user->save();
        }

        $this->flashSet('success', 'User updated successfully.');

        return $this->redirect($response, '/admin/users');
    }

    private function getRoles(): array
    {
        return [
            AuthorizationService::ROLE_ROOT,
            AuthorizationService::ROLE_HELPDESK,
            AuthorizationService::ROLE_ADMIN,
            AuthorizationService::ROLE_OPERATIONS,
            AuthorizationService::ROLE_SHOPPER,
        ];
    }
}
