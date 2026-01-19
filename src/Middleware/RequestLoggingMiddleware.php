<?php

namespace Cartly\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Cartly\Utilities\ApiLogger;

class RequestLoggingMiddleware implements MiddlewareInterface
{
    private ApiLogger $apiLogger;

    public function __construct()
    {
        $this->apiLogger = new ApiLogger();
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $startTime = microtime(true);
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        $ipAddress = $this->getClientIp($request);
        $userId = $request->getAttribute('user_id');

        // Log incoming request
        $this->apiLogger->logRequest($method, $path, $ipAddress, $userId);

        // Process the request
        $response = $handler->handle($request);

        // Calculate duration
        $duration = microtime(true) - $startTime;

        // Log response
        $this->apiLogger->logResponse($method, $path, $response->getStatusCode(), $duration, $userId);

        return $response;
    }

    /**
     * Get client IP address
     *
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for IP from shared internet
        if (!empty($serverParams['HTTP_CLIENT_IP'])) {
            return $serverParams['HTTP_CLIENT_IP'];
        }

        // Check for IP passed from proxy
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            // Can be a comma-separated list, take the first IP
            $ips = explode(',', $serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        // Fall back to REMOTE_ADDR
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
