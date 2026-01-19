<?php

namespace App\Middleware;

use App\Services\AuthorizationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RequirePermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthorizationService $authorization,
        private string $permission,
        private string $loginPath = '/admin/login'
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            $response = new Response();
            return $response
                ->withStatus(302)
                ->withHeader('Location', $this->loginPath);
        }

        $role = $_SESSION['user_role'] ?? null;
        if (!$this->authorization->roleHasPermission($role, $this->permission)) {
            $response = new Response();
            $response->getBody()->write('<h1>403 Forbidden</h1><p>You do not have permission to access this page.</p>');
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'text/html');
        }

        return $handler->handle($request);
    }
}
