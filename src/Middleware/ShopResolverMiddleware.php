<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerInterface;
use App\Models\ShopDomain;

class ShopResolverMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $request->getUri()->getHost();

        $domain = ShopDomain::where('domain', $host)->first();

        if ($domain && $domain->shop) {
            $request = $request->withAttribute('shop', $domain->shop);
        }

        return $handler->handle($request);
    }
}
