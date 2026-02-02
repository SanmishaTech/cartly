<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandlerInterface;
use App\Models\ShopDomain;
use Slim\Psr7\Response;

class ShopResolverMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $host = $request->getUri()->getHost();

        $appDomain = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN') ?? '';

        // Keep root domains on landing page.
        if ($host === 'localhost' || $host === '127.0.0.1' || ($appDomain !== '' && $host === $appDomain)) {
            return $handler->handle($request);
        }

        $domain = ShopDomain::with('shop.metadata')->where('domain', $host)->first();

        if ($domain && $domain->shop) {
            $request = $request->withAttribute('shop', $domain->shop);
        } else {
            $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?? '';
            if ($appUrl === '' && $appDomain !== '') {
                $scheme = $request->getUri()->getScheme() ?: 'http';
                $appUrl = $scheme . '://' . $appDomain;
            }

            if ($appUrl !== '') {
                $response = new Response();
                return $response->withStatus(302)->withHeader('Location', $appUrl);
            }
        }

        return $handler->handle($request);
    }
}
