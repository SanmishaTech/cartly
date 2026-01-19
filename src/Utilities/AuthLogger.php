<?php

namespace Cartly\Utilities;

use Monolog\Logger;
use Cartly\Factories\LoggerFactory;

class AuthLogger
{
    private Logger $logger;

    public function __construct()
    {
        $loggerFactory = new LoggerFactory();
        $this->logger = $loggerFactory->getLogger('auth');
    }

    /**
     * Log successful login
     */
    public function logLoginSuccess(int $userId, string $email, string $ipAddress): void
    {
        $this->logger->info('User login successful', [
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log failed login attempt
     */
    public function logLoginFailure(string $email, string $reason, string $ipAddress): void
    {
        $this->logger->warning('Login failed', [
            'email' => $email,
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log brute force attack
     */
    public function logBruteForceAttempt(string $email, string $ipAddress, int $attemptCount): void
    {
        $this->logger->critical('Brute force login attempt detected', [
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempt_count' => $attemptCount,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log logout
     */
    public function logLogout(int $userId, string $email, string $ipAddress): void
    {
        $this->logger->info('User logout', [
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $ipAddress,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log token generation
     */
    public function logTokenGenerated(int $userId, string $tokenType, string $expiresAt): void
    {
        $this->logger->info('Token generated', [
            'user_id' => $userId,
            'token_type' => $tokenType,
            'expires_at' => $expiresAt,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log invalid token attempt
     */
    public function logInvalidToken(string $reason, string $ipAddress): void
    {
        $this->logger->warning('Invalid token attempt', [
            'reason' => $reason,
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log password change
     */
    public function logPasswordChanged(int $userId, string $email): void
    {
        $this->logger->info('Password changed', [
            'user_id' => $userId,
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log password reset request
     */
    public function logPasswordResetRequested(string $email, string $ipAddress): void
    {
        $this->logger->info('Password reset requested', [
            'email' => $email,
            'ip_address' => $ipAddress,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log permission denied
     */
    public function logPermissionDenied(int $userId, string $action, string $resource): void
    {
        $this->logger->warning('Permission denied', [
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log sudo login action
     */
    public function logSudoLogin(int $actorId, string $actorEmail, int $targetId, string $targetEmail, ?int $shopId): void
    {
        $this->logger->warning('Sudo login', [
            'actor_id' => $actorId,
            'actor_email' => $actorEmail,
            'target_id' => $targetId,
            'target_email' => $targetEmail,
            'shop_id' => $shopId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
