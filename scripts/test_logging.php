#!/usr/bin/env php
<?php

require 'vendor/autoload.php';

use Cartly\Services\LoggerFactory;
use Cartly\Utilities\PaymentLogger;
use Cartly\Utilities\OrderLogger;
use Cartly\Utilities\AuthLogger;
use Cartly\Utilities\ApiLogger;
use Cartly\Utilities\ErrorLogger;

echo "=== Testing Monolog Implementation ===\n\n";

// Test 1: LoggerFactory
echo "Test 1: LoggerFactory - Create multiple channels\n";
try {
    $factory = new LoggerFactory();
    $appLogger = $factory->getLogger('app');
    $paymentLogger = $factory->getLogger('payment');
    $orderLogger = $factory->getLogger('order');
    $authLogger = $factory->getLogger('auth');
    $apiLogger = $factory->getLogger('api');
    $errorLogger = $factory->getLogger('error');
    
    echo "✓ PASS: All logger channels created\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 2: PaymentLogger
echo "Test 2: PaymentLogger - Log payment success\n";
try {
    $paymentLogger = new PaymentLogger();
    $paymentLogger->logPaymentSuccess(1001, 299.99, 'TXN123456', 'credit_card');
    echo "✓ PASS: Payment logged successfully\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 3: PaymentLogger - Log payment failure
echo "Test 3: PaymentLogger - Log payment failure\n";
try {
    $paymentLogger = new PaymentLogger();
    $paymentLogger->logPaymentFailure(1002, 149.99, 'Declined by issuer');
    echo "✓ PASS: Payment failure logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 4: OrderLogger
echo "Test 4: OrderLogger - Log order created\n";
try {
    $orderLogger = new OrderLogger();
    $orderLogger->logOrderCreated(1001, 5, 599.99, [
        ['product_id' => 1, 'quantity' => 2],
        ['product_id' => 3, 'quantity' => 1],
    ]);
    echo "✓ PASS: Order creation logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 5: OrderLogger - Log status change
echo "Test 5: OrderLogger - Log order status change\n";
try {
    $orderLogger = new OrderLogger();
    $orderLogger->logOrderStatusChanged(1001, 'pending', 'processing', 'Payment confirmed');
    echo "✓ PASS: Order status change logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 6: OrderLogger - Log stock reduction
echo "Test 6: OrderLogger - Log stock reduction\n";
try {
    $orderLogger = new OrderLogger();
    $orderLogger->logStockReduced(5, 2, 18, 1001);
    echo "✓ PASS: Stock reduction logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 7: AuthLogger
echo "Test 7: AuthLogger - Log login success\n";
try {
    $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
    
    $authLogger = new AuthLogger();
    $authLogger->logLoginSuccess(42, 'user@example.com', '192.168.1.100');
    echo "✓ PASS: Login success logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 8: AuthLogger - Log failed login
echo "Test 8: AuthLogger - Log login failure\n";
try {
    $authLogger = new AuthLogger();
    $authLogger->logLoginFailure('user@example.com', 'Invalid password', '192.168.1.101');
    echo "✓ PASS: Login failure logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 9: AuthLogger - Log brute force
echo "Test 9: AuthLogger - Log brute force attempt\n";
try {
    $authLogger = new AuthLogger();
    $authLogger->logBruteForceAttempt('hacker@evil.com', '192.168.1.102', 15);
    echo "✓ PASS: Brute force attempt logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 10: ApiLogger
echo "Test 10: ApiLogger - Log API request\n";
try {
    $apiLogger = new ApiLogger();
    $apiLogger->logRequest('POST', '/api/orders', '192.168.1.100', 42);
    echo "✓ PASS: API request logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 11: ApiLogger - Log API response
echo "Test 11: ApiLogger - Log API response\n";
try {
    $apiLogger = new ApiLogger();
    $apiLogger->logResponse('POST', '/api/orders', 201, 0.125, 42);
    echo "✓ PASS: API response logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 12: ApiLogger - Log rate limit exceeded
echo "Test 12: ApiLogger - Log rate limit exceeded\n";
try {
    $apiLogger = new ApiLogger();
    $apiLogger->logRateLimitExceeded('/api/auth/login', 'POST', '192.168.1.102', 5, 900);
    echo "✓ PASS: Rate limit exceeded logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 13: ErrorLogger
echo "Test 13: ErrorLogger - Log exception\n";
try {
    $errorLogger = new ErrorLogger();
    try {
        throw new Exception('Test exception message');
    } catch (\Exception $e) {
        $errorLogger->logException($e, 42);
    }
    echo "✓ PASS: Exception logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 14: ErrorLogger - Log database error
echo "Test 14: ErrorLogger - Log database error\n";
try {
    $errorLogger = new ErrorLogger();
    $errorLogger->logDatabaseError(
        'SELECT * FROM users WHERE id = ?',
        'Connection lost',
        42
    );
    echo "✓ PASS: Database error logged\n\n";
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

// Test 15: Log directory verification
echo "Test 15: Verify log files created\n";
try {
    $logDir = __DIR__ . '/storage/logs';
    if (is_dir($logDir)) {
        $logFiles = glob($logDir . '/*.log');
        $fileCount = count($logFiles);
        echo "✓ PASS: Found {$fileCount} log files in {$logDir}\n";
        echo "  Files: " . implode(', ', array_map('basename', $logFiles)) . "\n\n";
    } else {
        echo "✗ FAIL: Log directory not created\n\n";
    }
} catch (\Exception $e) {
    echo "✗ FAIL: {$e->getMessage()}\n\n";
}

echo "=== Monolog Tests Complete ===\n";
