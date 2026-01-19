<?php

namespace Cartly\Utilities;

use Monolog\Logger;
use Cartly\Services\LoggerFactory;
use Throwable;

class ErrorLogger
{
    private Logger $logger;

    public function __construct()
    {
        $loggerFactory = new LoggerFactory();
        $this->logger = $loggerFactory->getLogger('error');
    }

    /**
     * Log exception
     */
    public function logException(Throwable $exception, ?int $userId = null): void
    {
        $this->logger->error('Exception occurred', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'trace' => $exception->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log fatal error
     */
    public function logFatalError(string $message, string $file, int $line, array $context = []): void
    {
        $this->logger->critical('Fatal error', array_merge([
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ], $context));
    }

    /**
     * Log database error
     */
    public function logDatabaseError(string $query, string $error, ?int $userId = null): void
    {
        $this->logger->error('Database error', [
            'query' => $query,
            'error' => $error,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log file operation error
     */
    public function logFileError(string $operation, string $filePath, string $error): void
    {
        $this->logger->error('File operation error', [
            'operation' => $operation,
            'file_path' => $filePath,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log external API error
     */
    public function logExternalApiError(string $apiName, string $endpoint, int $statusCode, string $response): void
    {
        $this->logger->error('External API error', [
            'api_name' => $apiName,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => substr($response, 0, 500), // Limit response size
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log timeout
     */
    public function logTimeout(string $operation, int $timeoutSeconds): void
    {
        $this->logger->warning('Operation timeout', [
            'operation' => $operation,
            'timeout_seconds' => $timeoutSeconds,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log memory error
     */
    public function logMemoryError(int $currentUsage, int $limit): void
    {
        $this->logger->critical('Memory limit error', [
            'current_usage' => $currentUsage,
            'limit' => $limit,
            'usage_percent' => round(($currentUsage / $limit) * 100, 2),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log config error
     */
    public function logConfigError(string $message, array $context = []): void
    {
        $this->logger->critical('Configuration error', array_merge([
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ], $context));
    }
}
