<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerInterface;
use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionEnforcerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $shop = $request->getAttribute('shop');
        $state = 'unknown';
        $subscription = null;

        if ($shop) {
            $subscription = $shop->subscription;
            $now = Carbon::now();

            if (!$subscription) {
                $state = 'expired';
            } else {
                $expires = $subscription->expires_at ? Carbon::parse($subscription->expires_at) : null;

                if ($subscription->status === 'trial' && $expires && $expires->isFuture()) {
                    $state = 'trial';
                } elseif ($subscription->status === 'active' && $expires && $expires->isFuture()) {
                    $state = 'active';
                } else {
                    $state = 'expired';
                }
            }

            $request = $request
                ->withAttribute('subscription', $subscription)
                ->withAttribute('subscriptionState', $state);
        }

        // Soft gating: let request proceed, views can show prompts based on state
        return $handler->handle($request);
    }
}
