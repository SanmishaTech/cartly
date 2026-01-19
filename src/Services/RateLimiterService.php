<?php

namespace Cartly\Services;

use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;

class RateLimiterService
{
    private RateLimiterFactory $factory;

    public function __construct()
    {
        $this->factory = new RateLimiterFactory([
            'id' => 'api_limiter',
            'policy' => 'sliding_window',
            'limit' => 100,
            'interval' => '1 hour',
        ], new InMemoryStorage());
    }

    /**
     * Get a rate limiter for a specific endpoint and identifier
     *
     * @param string $endpoint API endpoint (e.g., 'login', 'create_order')
     * @param string $identifier User IP, user ID, or api token
     * @param array $config Optional configuration: ['limit' => 10, 'interval' => '1 minute']
     * @return \Symfony\Component\RateLimiter\RateLimiterInterface
     */
    public function getLimiter(string $endpoint, string $identifier, array $config = []): object
    {
        $limits = $this->getEndpointLimits();
        $endpointConfig = $limits[$endpoint] ?? $limits['default'];

        if (!empty($config)) {
            $endpointConfig = array_merge($endpointConfig, $config);
        }

        $key = "{$endpoint}:{$identifier}";

        $factory = new RateLimiterFactory([
            'id' => $key,
            'policy' => 'sliding_window',
            'limit' => $endpointConfig['limit'],
            'interval' => $endpointConfig['interval'],
        ], new InMemoryStorage());

        return $factory->create($key);
    }

    /**
     * Get configured rate limits per endpoint
     *
     * @return array
     */
    private function getEndpointLimits(): array
    {
        return [
            'default' => [
                'limit' => 100,
                'interval' => '1 hour',
            ],
            'login' => [
                'limit' => 5,
                'interval' => '15 minutes',
            ],
            'verify' => [
                'limit' => 10,
                'interval' => '1 hour',
            ],
            'create_order' => [
                'limit' => 50,
                'interval' => '1 hour',
            ],
            'list_orders' => [
                'limit' => 100,
                'interval' => '1 hour',
            ],
            'create_plan' => [
                'limit' => 20,
                'interval' => '1 hour',
            ],
            'update_plan' => [
                'limit' => 20,
                'interval' => '1 hour',
            ],
            'delete_plan' => [
                'limit' => 10,
                'interval' => '1 hour',
            ],
        ];
    }

    /**
     * Check if request exceeds rate limit
     *
     * @param object $limiter
     * @return bool True if within limit, false if exceeded
     */
    public function isWithinLimit(object $limiter): bool
    {
        try {
            $limiter->consume();
            return true;
        } catch (\Symfony\Component\RateLimiter\Exception\RateLimitExceededException $e) {
            return false;
        }
    }

    /**
     * Get remaining requests for a limiter
     *
     * @param object $limiter
     * @return int
     */
    public function getRemainingRequests(object $limiter): int
    {
        $limit = $limiter->getLimit();
        $availableTokens = $limiter->getAvailableTokens();
        return max(0, (int) $availableTokens);
    }

    /**
     * Get retry-after seconds
     *
     * @param object $limiter
     * @return int Seconds until next request allowed
     */
    public function getRetryAfter(object $limiter): int
    {
        return (int) ceil($limiter->getLimit()->getRetryAfter()->getTimestamp() - time());
    }
}
