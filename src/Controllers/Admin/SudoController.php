<?php

namespace App\Controllers\Admin;

use App\Models\ShopUser;
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
            'email' => 'email',
            'shop' => 'shop_name',
            'status' => 'status',
        ];

        $query = User::query()
            ->with(['shopUsers.shop.domains'])
            ->whereHas('shopUsers', function ($q) {
                $q->where('role', ShopUser::ROLE_OWNER);
            })
            ->select('users.*');

        $pager = $this->paginationService()->paginate($query, $params, [
            'basePath' => '/admin/sudo',
            'sortMap' => $sortMap,
            'search' => function ($q, $value) {
                $q->where(function ($inner) use ($value) {
                    $inner->where('users.email', 'like', '%' . $value . '%')
                        ->orWhereHas('shopUsers.shop', function ($shopQuery) use ($value) {
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
        $target = User::with('shopUsers')->find($targetId);
        if (!$target) {
            $this->flashSet('error', 'User not found.');
            return $this->redirect($response, '/admin/sudo');
        }

        $ownerMembership = $target->shopUsers->firstWhere('role', ShopUser::ROLE_OWNER)
            ?? $target->shopUsers->first();
        if (!$ownerMembership || $target->status !== 'active') {
            $this->flashSet('error', 'Only active shop owners can be accessed.');
            return $this->redirect($response, '/admin/sudo');
        }

        $shopId = (int)$ownerMembership->shop_id;

        $actorId = (int)($_SESSION['user_id'] ?? 0);
        $actorEmail = (string)($_SESSION['user_email'] ?? '');

        $_SESSION['sudo_actor_id'] = $actorId;
        $_SESSION['sudo_actor_email'] = $actorEmail;
        $_SESSION['user_id'] = $target->id;
        $_SESSION['user_email'] = $target->email;
        $_SESSION['user_name'] = $target->email;
        $_SESSION['user_role'] = $ownerMembership->role;
        $_SESSION['shop_id'] = $shopId;

        $logger = new AuthLogger();
        $logger->logSudoLogin(
            $actorId,
            $actorEmail,
            $target->id,
            $target->email,
            $shopId
        );

        $this->flashSet('success', 'Sudo login successful.');

        return $this->redirect($response, '/admin/dashboard');
    }
}
