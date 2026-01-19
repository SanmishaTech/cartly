<?php

namespace App\Controllers\Admin;

use App\Models\Package;
use App\Services\AuthorizationService;
use Slim\Psr7\Response;

class DashboardController extends AppController
{
    /**
     * Root dashboard
     */
    public function index($request, Response $response): Response
    {
        $this->startSession();

        $role = $_SESSION['user_role'] ?? null;
        if (in_array($role, [AuthorizationService::ROLE_ROOT, AuthorizationService::ROLE_HELPDESK], true)) {
            $packages = Package::all();
            return $this->render($response, 'dashboard/root.twig', [
                'session' => $_SESSION,
                'packages' => $packages,
            ]);
        }

        return $this->render($response, 'dashboard/admin.twig', [
            'session' => $_SESSION,
        ]);
    }
}
