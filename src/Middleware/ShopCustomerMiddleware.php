<?php

namespace App\Middleware;

use App\Models\ShopCustomer;
use Carbon\Carbon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Records shop_customers when a logged-in user is seen on a shop storefront.
 * NOT for auth or permissions. Throttles last_seen_at updates to once per 5 min per (shop, user).
 */
class ShopCustomerMiddleware implements MiddlewareInterface
{
    private const THROTTLE_SECONDS = 300;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $shop = $request->getAttribute('shop');
        if (!$shop) {
            return $handler->handle($request);
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return $handler->handle($request);
        }

        $throttleKey = '_shop_customer_seen_' . $shop->id;
        $lastSeen = (int)($_SESSION[$throttleKey] ?? 0);
        if ($lastSeen > 0 && (time() - $lastSeen) < self::THROTTLE_SECONDS) {
            return $handler->handle($request);
        }

        $_SESSION[$throttleKey] = time();

        $now = Carbon::now();
        $record = ShopCustomer::where('shop_id', $shop->id)->where('user_id', $userId)->first();
        if ($record) {
            $record->update(['last_seen_at' => $now]);
        } else {
            ShopCustomer::create([
                'shop_id' => $shop->id,
                'user_id' => $userId,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);
        }

        return $handler->handle($request);
    }
}
