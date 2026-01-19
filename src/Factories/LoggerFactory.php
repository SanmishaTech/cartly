<?php

namespace Cartly\Factories;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Processors\ProcessorInterface;

class LoggerFactory
{
    private static array $loggers = [];
    private string $basePath;

    public function __construct(string $basePath = __DIR__ . '/../../storage/logs')
    {
        $this->basePath = $basePath;
        $this->ensureLogDirectory();
    }

    /**
     * Get or create a logger channel
     *
     * @param string $channel Channel name (api, payment, order, error, auth, etc.)
     * @return Logger
     */
    public function getLogger(string $channel = 'app'): Logger
    {
        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        $logger = new Logger($channel);

        // Add processors to add context
        // Add unique request ID processor (simple version)
        $logger->pushProcessor(function ($record) {
            if (empty($record['extra']['request_id'])) {
                $record['extra']['request_id'] = uniqid('req_', true);
            }
            return $record;
        });

        // Add handlers based on channel
        match ($channel) {
            'payment' => $this->setupPaymentLogger($logger),
            'order' => $this->setupOrderLogger($logger),
            'auth' => $this->setupAuthLogger($logger),
            'api' => $this->setupApiLogger($logger),
            'error' => $this->setupErrorLogger($logger),
            'database' => $this->setupDatabaseLogger($logger),
            'security' => $this->setupSecurityLogger($logger),
            default => $this->setupDefaultLogger($logger),
        };

        self::$loggers[$channel] = $logger;
        return $logger;
    }

    /**
     * Setup default application logger
     */
    private function setupDefaultLogger(Logger $logger): void
    {
        // Log to rotating file (rotated daily, keep 14 days)
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/app.log',
            14, // Max files
            Logger::INFO
        ));

        // Also log errors to error handler
        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::ERROR));
    }

    /**
     * Setup payment logger (high priority - all levels)
     */
    private function setupPaymentLogger(Logger $logger): void
    {
        // Log all payment transactions
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/payment.log',
            30, // Keep 30 days of payment logs
            Logger::DEBUG
        ));

        // Critical payment errors go to error log
        $logger->pushHandler(new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            Logger::CRITICAL
        ));
    }

    /**
     * Setup order logger
     */
    private function setupOrderLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/order.log',
            14,
            Logger::INFO
        ));
    }

    /**
     * Setup authentication logger (security-sensitive)
     */
    private function setupAuthLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/auth.log',
            30, // Keep longer for security audit trail
            Logger::DEBUG
        ));
    }

    /**
     * Setup API request logger
     */
    private function setupApiLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/api.log',
            7, // Rotate weekly
            Logger::INFO
        ));
    }

    /**
     * Setup error logger (critical logs)
     */
    private function setupErrorLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/error.log',
            14,
            Logger::ERROR
        ));

        // Also log to system error log
        $logger->pushHandler(new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            Logger::ERROR
        ));
    }

    /**
     * Setup database logger
     */
    private function setupDatabaseLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/database.log',
            7,
            Logger::DEBUG
        ));
    }

    /**
     * Setup security logger (for suspicious activities)
     */
    private function setupSecurityLogger(Logger $logger): void
    {
        $logger->pushHandler(new RotatingFileHandler(
            $this->basePath . '/security.log',
            30,
            Logger::WARNING
        ));

        $logger->pushHandler(new ErrorLogHandler(
            ErrorLogHandler::OPERATING_SYSTEM,
            Logger::CRITICAL
        ));
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    /**
     * Get all active loggers
     *
     * @return array
     */
    public static function getActiveLoggers(): array
    {
        return self::$loggers;
    }

    /**
     * Clear all cached loggers
     */
    public static function clearLoggers(): void
    {
        self::$loggers = [];
    }
}
