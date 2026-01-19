<?php

namespace Cartly\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Cartly\Services\RateLimiterService;

class RateLimiterMiddleware implements MiddlewareInterface
{
    private RateLimiterService $rateLimiter;

    public function __construct(RateLimiterService $rateLimiter)
    {
        $this->rateLimiter = $rateLimiter;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Get the client identifier (IP address or user ID if authenticated)
        $clientId = $this->getClientIdentifier($request);

        // Determine the endpoint from the URI path
        $path = $request->getUri()->getPath();
        $endpoint = $this->getEndpointName($path);

        // Get the rate limiter for this endpoint and client
        $limiter = $this->rateLimiter->getLimiter($endpoint, $clientId);

        // Check if within rate limit
        if (!$this->rateLimiter->isWithinLimit($limiter)) {
            $retryAfter = $this->rateLimiter->getRetryAfter($limiter);

            // Return 429 Too Many Requests
            $response = new \Slim\Psr7\Response();
            $response = $response
                ->withStatus(429)
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', '100')
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string) (time() + $retryAfter));

            $response->getBody()->write(json_encode([
                'error' => 'Rate limit exceeded',
                'message' => "Too many requests. Please retry after {$retryAfter} seconds.",
                'retry_after' => $retryAfter,
            ]));

            return $response;
        }

        // Proceed with request
        $response = $handler->handle($request);

        // Add rate limit headers to response
        $remaining = $this->rateLimiter->getRemainingRequests($limiter);
        return $response
            ->withHeader('X-RateLimit-Limit', '100')
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', (string) (time() + 3600));
    }

    /**
     * Get client identifier (IP or user ID)
     *
     * @param Request $request
     * @return string
     */
    private function getClientIdentifier(Request $request): string
    {
        // Check for user ID from JWT token
        $userId = $request->getAttribute('user_id');
        if ($userId) {
            return "user_{$userId}";
        }

        // Fall back to client IP
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Extract endpoint name from request path
     *
     * @param string $path
     * @return string
     */
    private function getEndpointName(string $path): string
    {
        // Map paths to endpoint names
        $mappings = [
            '/api/auth/login' => 'login',
            '/api/auth/verify' => 'verify',
            '/api/auth/me' => 'me',
            '/api/auth/logout' => 'logout',
            '/api/orders' => 'list_orders',
        ];

        // Check exact match first
        if (isset($mappings[$path])) {
            return $mappings[$path];
        }

        // Check patterns
        if (preg_match('/^\/api\/orders\/(create|POST)/', $path)) {
            return 'create_order';
        }

        // Default endpoint
        return 'default';
    }
}
