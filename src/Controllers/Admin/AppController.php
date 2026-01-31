<?php

namespace App\Controllers\Admin;

use Slim\Psr7\Response;
use Slim\Views\Twig;
use App\Models\Shop;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\FlashService;
use App\Helpers\PaginationHelper;
use App\Helpers\SessionHelper;
use App\Services\PaginationService;
use App\Services\ThemeResolver;

class AppController
{
    protected ?PaginationHelper $paginationHelper = null;
    protected ?SessionHelper $sessionHelper = null;
    protected ?PaginationService $paginationService = null;

    public function __construct(
        protected Twig $view,
        protected ThemeResolver $themeResolver
    ) {}

    protected function render(Response $response, string $viewName, array $data = []): Response
    {
        $this->startSession();
        if (!array_key_exists('shop', $data)) {
            $shopId = (int)($_SESSION['shop_id'] ?? 0);
            if ($shopId > 0) {
                $userId = (int)($_SESSION['user_id'] ?? 0);
                $user = $userId > 0 ? User::find($userId) : null;
                if ($user && $user->canManageShop($shopId)) {
                    $data['shop'] = Shop::with('domains')->find($shopId);
                } else {
                    $data['shop'] = null;
                    $_SESSION['shop_id'] = 0;
                    if ($user) {
                        $firstShopId = $user->getFirstManagedShopId();
                        if ($firstShopId) {
                            $_SESSION['shop_id'] = $firstShopId;
                            $_SESSION['user_role'] = $user->getEffectiveRoleForShop($firstShopId);
                        }
                    }
                }
            }
        }
        if (!array_key_exists('shop_preview_url', $data) && !empty($data['shop'])) {
            $previewUrl = $this->buildShopPreviewUrl($data['shop']);
            if ($previewUrl) {
                $data['shop_preview_url'] = $previewUrl;
            }
        }
        if (!array_key_exists('can', $data)) {
            $role = $_SESSION['user_role'] ?? null;
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $shopId = (int)($_SESSION['shop_id'] ?? 0);
            if ($userId > 0) {
                $user = User::find($userId);
                if ($user) {
                    $role = $shopId > 0 ? $user->getEffectiveRoleForShop($shopId) : $user->global_role;
                }
            }
            $authorization = new AuthorizationService();
            $data['can'] = [
                'dashboard_access' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_DASHBOARD_ACCESS),
                'packages_manage' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_PACKAGES_MANAGE),
                'shops_manage' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_SHOPS_MANAGE),
                'users_manage' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_USERS_MANAGE),
                'subscriptions_manage' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_SUBSCRIPTIONS_MANAGE),
                'support_sudo' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_SUPPORT_SUDO),
                'setup_access' => $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_SETUP_ACCESS),
            ];
        }
        if (!array_key_exists('success', $data)) {
            $data['success'] = $this->flashGet('success');
        }
        if (!array_key_exists('error', $data)) {
            $data['error'] = $this->flashGet('error');
        }
        if (!array_key_exists('info', $data)) {
            $data['info'] = $this->flashGet('info');
        }
        if (!array_key_exists('warning', $data)) {
            $data['warning'] = $this->flashGet('warning');
        }
        return $this->view->render($response, $viewName, $data);
    }

    protected function redirect(Response $response, string $location, int $status = 302): Response
    {
        return $response->withStatus($status)->withHeader('Location', $location);
    }

    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function flashSet(string $key, mixed $value): void
    {
        FlashService::set($key, $value);
    }

    protected function flashGet(string $key, mixed $default = null): mixed
    {
        return FlashService::get($key, $default);
    }

    protected function pagination(): PaginationHelper
    {
        if ($this->paginationHelper === null) {
            $this->paginationHelper = new PaginationHelper();
        }
        return $this->paginationHelper;
    }

    protected function session(): SessionHelper
    {
        if ($this->sessionHelper === null) {
            $this->sessionHelper = new SessionHelper();
        }
        return $this->sessionHelper;
    }

    protected function paginationService(): PaginationService
    {
        if ($this->paginationService === null) {
            $this->paginationService = new PaginationService($this->pagination());
        }
        return $this->paginationService;
    }

    protected function themeResolver(): ThemeResolver
    {
        return $this->themeResolver;
    }

    private function buildShopPreviewUrl(Shop $shop): ?string
    {
        $primaryDomain = null;
        if ($shop->relationLoaded('domains')) {
            foreach ($shop->domains as $domain) {
                if ($domain->is_primary) {
                    $primaryDomain = $domain->domain;
                    break;
                }
            }
            if (!$primaryDomain && $shop->domains->count() > 0) {
                $primaryDomain = $shop->domains->first()->domain;
            }
        } else {
            $primaryDomain = $shop->domains()
                ->where('is_primary', true)
                ->value('domain');
            if (!$primaryDomain) {
                $primaryDomain = $shop->domains()->value('domain');
            }
        }

        if (!$primaryDomain) {
            return null;
        }

        return 'https://' . $primaryDomain;
    }

    protected function formatValitronErrors(array $errors): array
    {
        $flat = [];
        foreach ($errors as $field => $messages) {
            if (is_array($messages) && count($messages) > 0) {
                $flat[$field] = $messages[0];
            }
        }
        return $flat;
    }
}
