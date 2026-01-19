<?php

namespace Cartly\Utilities;

use Monolog\Logger;
use Cartly\Services\LoggerFactory;

class PaymentLogger
{
    private Logger $logger;

    public function __construct()
    {
        $loggerFactory = new LoggerFactory();
        $this->logger = $loggerFactory->getLogger('payment');
    }

    /**
     * Log successful payment
     */
    public function logPaymentSuccess(int $orderId, float $amount, string $transactionId, string $method): void
    {
        $this->logger->info('Payment successful', [
            'order_id' => $orderId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'method' => $method,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log payment failure
     */
    public function logPaymentFailure(int $orderId, float $amount, string $reason, ?string $transactionId = null): void
    {
        $this->logger->error('Payment failed', [
            'order_id' => $orderId,
            'amount' => $amount,
            'reason' => $reason,
            'transaction_id' => $transactionId,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log payment refund
     */
    public function logPaymentRefund(int $orderId, float $amount, string $transactionId, string $reason): void
    {
        $this->logger->warning('Payment refund initiated', [
            'order_id' => $orderId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log payment retry
     */
    public function logPaymentRetry(int $orderId, int $attemptNumber, string $reason): void
    {
        $this->logger->info('Payment retry', [
            'order_id' => $orderId,
            'attempt' => $attemptNumber,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log payment gateway error
     */
    public function logGatewayError(string $gateway, string $error, int $statusCode): void
    {
        $this->logger->critical('Payment gateway error', [
            'gateway' => $gateway,
            'error' => $error,
            'status_code' => $statusCode,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
