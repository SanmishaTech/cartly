<?php

namespace App\Controllers\Admin;

use App\Models\User;
use App\Services\AuthorizationService;
use Cartly\Utilities\AuthLogger;
use Slim\Psr7\Response;

class SudoController extends AppController
{
    public function index($request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $sortMap = [
            'name' => 'name',
            'email' => 'email',
            'shop' => 'shop_name',
            'status' => 'status',
        ];

        $query = User::query()
            ->with('shop.domains')
            ->leftJoin('shops', 'users.shop_id', '=', 'shops.id')
            ->select('users.*', 'shops.shop_name as shop_name')
            ->where('users.role', AuthorizationService::ROLE_ADMIN);

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/sudo',
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

        return $this->render($response, 'sudo/list.twig', [
            'pager' => $pager,
        ]);
    }

    public function login($request, Response $response): Response
    {
        $this->startSession();
        $targetId = (int)$request->getAttribute('id');
        $target = User::find($targetId);
        if (!$target) {
            $this->flashSet('error', 'User not found.');
            return $this->redirect($response, '/admin/sudo');
        }

        if ($target->role !== AuthorizationService::ROLE_ADMIN || $target->status !== 'active') {
            $this->flashSet('error', 'Only active shop admins can be accessed.');
            return $this->redirect($response, '/admin/sudo');
        }

        if (!$target->shop_id) {
            $this->flashSet('error', 'Selected admin is not linked to a shop.');
            return $this->redirect($response, '/admin/sudo');
        }

        $actorId = (int)($_SESSION['user_id'] ?? 0);
        $actorEmail = (string)($_SESSION['user_email'] ?? '');

        $_SESSION['sudo_actor_id'] = $actorId;
        $_SESSION['sudo_actor_email'] = $actorEmail;
        $_SESSION['user_id'] = $target->id;
        $_SESSION['user_email'] = $target->email;
        $_SESSION['user_name'] = $target->name;
        $_SESSION['user_role'] = $target->role;
        $_SESSION['shop_id'] = $target->shop_id;

        $logger = new AuthLogger();
        $logger->logSudoLogin(
            $actorId,
            $actorEmail,
            $target->id,
            $target->email,
            $target->shop_id
        );

        $this->flashSet('success', 'Sudo login successful.');

        return $this->redirect($response, '/admin/dashboard');
    }
}
