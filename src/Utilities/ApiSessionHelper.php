<?php

namespace App\Utilities;

use App\Services\AuthorizationService;
use Slim\Psr7\Response;

/**
 * Helper class for session-based API authentication
 * 
 * Used by API endpoints that require user to be logged in via form-based auth.
 * These are action endpoints (cart, coupon, wishlist), not authentication endpoints.
 */
class ApiSessionHelper
{
    /**
     * Ensure session is started
     */
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if user is logged in (has active session)
     */
    public static function isAuthenticated(): bool
    {
        self::ensureSession();
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get current user ID from session
     */
    public static function getUserId(): ?int
    {
        self::ensureSession();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current user data from session
     */
    public static function getUser(): ?array
    {
        self::ensureSession();
        
        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'shop_id' => $_SESSION['shop_id'] ?? null,
        ];
    }

    /**
     * Get user role from session
     */
    public static function getUserRole(): ?string
    {
        self::ensureSession();
        return $_SESSION['user_role'] ?? null;
    }

    /**
     * Check if user has specific role
     */
    public static function hasRole(string $role): bool
    {
        return self::getUserRole() === $role;
    }

    /**
     * Check if user is admin/root
     */
    public static function isAdmin(): bool
    {
        $role = self::getUserRole();
        $authorization = new AuthorizationService();
        return $authorization->roleHasPermission($role, AuthorizationService::PERMISSION_DASHBOARD_ACCESS);
    }

    /**
     * Respond with 401 Unauthorized
     */
    public static function respondUnauthorized(Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Please login to perform this action'
        ]));

        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Respond with 403 Forbidden
     */
    public static function respondForbidden(Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'You do not have permission to perform this action'
        ]));

        return $response
            ->withStatus(403)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Respond with success
     */
    public static function respondSuccess(Response $response, array $data, int $status = 200): Response
    {
        $payload = array_merge(['success' => true], $data);
        $response->getBody()->write(json_encode($payload));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    /**
     * Respond with error
     */
    public static function respondError(Response $response, string $message, int $status = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => $message,
        ]));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
