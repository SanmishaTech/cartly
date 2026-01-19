<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Models\User;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $user = null;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        // Check for user_id in session
        if (isset($_SESSION['user_id'])) {
            try {
                $user = User::find($_SESSION['user_id']);
                
                // Verify user is still active
                if ($user && !$user->isActive()) {
                    unset(
                        $_SESSION['user_id'],
                        $_SESSION['user_email'],
                        $_SESSION['user_name'],
                        $_SESSION['user_role'],
                        $_SESSION['shop_id']
                    );
                    $user = null;
                }
            } catch (\Exception $e) {
                // Silently fail and treat as no user
                $user = null;
            }
        }
        
        // Attach user to request
        if ($user) {
            $request = $request->withAttribute('user', $user);
        }
        
        return $handler->handle($request);
    }
}

