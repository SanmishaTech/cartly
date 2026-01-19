<?php

namespace Cartly\Utilities;

use Monolog\Logger;
use Cartly\Factories\LoggerFactory;

class ApiLogger
{
    private Logger $logger;

    public function __construct()
    {
        $loggerFactory = new LoggerFactory();
        $this->logger = $loggerFactory->getLogger('api');
    }

    /**
     * Log API request
     */
    public function logRequest(string $method, string $path, string $ipAddress, ?int $userId = null): void
    {
        $this->logger->info('API request', [
            'method' => $method,
            'path' => $path,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log API response
     */
    public function logResponse(string $method, string $path, int $statusCode, float $duration, ?int $userId = null): void
    {
        $level = match (true) {
            $statusCode >= 500 => Logger::ERROR,
            $statusCode >= 400 => Logger::WARNING,
            default => Logger::INFO,
        };

        $this->logger->log($level, 'API response', [
            'method' => $method,
            'path' => $path,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log validation error
     */
    public function logValidationError(string $path, string $method, string $errorMessage, ?int $userId = null): void
    {
        $this->logger->warning('Validation error', [
            'method' => $method,
            'path' => $path,
            'error' => $errorMessage,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log rate limit exceeded
     */
    public function logRateLimitExceeded(string $path, string $method, string $ipAddress, int $limit, int $window): void
    {
        $this->logger->warning('Rate limit exceeded', [
            'method' => $method,
            'path' => $path,
            'ip_address' => $ipAddress,
            'limit' => $limit,
            'window_seconds' => $window,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log API error
     */
    public function logError(string $path, string $method, string $errorMessage, string $exception, ?int $userId = null): void
    {
        $this->logger->error('API error', [
            'method' => $method,
            'path' => $path,
            'error' => $errorMessage,
            'exception' => $exception,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log deprecation warning
     */
    public function logDeprecatedEndpoint(string $path, string $method, string $replacement): void
    {
        $this->logger->warning('Deprecated endpoint accessed', [
            'method' => $method,
            'path' => $path,
            'use_instead' => $replacement,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log suspicious API activity
     */
    public function logSuspiciousActivity(string $activity, string $path, string $ipAddress, array $details = []): void
    {
        $this->logger->critical('Suspicious API activity detected', array_merge([
            'activity' => $activity,
            'path' => $path,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ], $details));
    }
}
