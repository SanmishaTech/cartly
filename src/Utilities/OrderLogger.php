<?php

namespace Cartly\Utilities;

use Monolog\Logger;
use Cartly\Services\LoggerFactory;

class OrderLogger
{
    private Logger $logger;

    public function __construct()
    {
        $loggerFactory = new LoggerFactory();
        $this->logger = $loggerFactory->getLogger('order');
    }

    /**
     * Log order creation
     */
    public function logOrderCreated(int $orderId, int $shopId, float $total, array $items): void
    {
        $this->logger->info('Order created', [
            'order_id' => $orderId,
            'shop_id' => $shopId,
            'total_amount' => $total,
            'item_count' => count($items),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log order status change
     */
    public function logOrderStatusChanged(int $orderId, string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $this->logger->info('Order status changed', [
            'order_id' => $orderId,
            'from_status' => $oldStatus,
            'to_status' => $newStatus,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log order shipment
     */
    public function logOrderShipped(int $orderId, string $carrier, string $trackingNumber): void
    {
        $this->logger->info('Order shipped', [
            'order_id' => $orderId,
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log order cancellation
     */
    public function logOrderCancelled(int $orderId, string $reason, int $initiatedBy): void
    {
        $this->logger->warning('Order cancelled', [
            'order_id' => $orderId,
            'reason' => $reason,
            'initiated_by' => $initiatedBy,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log order return
     */
    public function logOrderReturned(int $orderId, string $reason): void
    {
        $this->logger->info('Order returned', [
            'order_id' => $orderId,
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log inventory stock reduction
     */
    public function logStockReduced(int $productId, int $quantity, int $remainingStock, int $orderId): void
    {
        $this->logger->info('Stock reduced', [
            'product_id' => $productId,
            'quantity_sold' => $quantity,
            'remaining_stock' => $remainingStock,
            'order_id' => $orderId,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log out of stock event
     */
    public function logOutOfStock(int $productId): void
    {
        $this->logger->warning('Product out of stock', [
            'product_id' => $productId,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Log low stock warning
     */
    public function logLowStock(int $productId, int $currentStock, int $threshold): void
    {
        $this->logger->warning('Product low stock', [
            'product_id' => $productId,
            'current_stock' => $currentStock,
            'threshold' => $threshold,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}
