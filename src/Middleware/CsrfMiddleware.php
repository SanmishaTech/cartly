<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use Slim\Views\Twig;

class CsrfMiddleware implements MiddlewareInterface
{
    private string $sessionKey;

    public function __construct(
        private Twig $twig,
        string $sessionKey = '_csrf_token'
    ) {
        $this->sessionKey = $sessionKey;
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = bin2hex(random_bytes(32));
        }

        $token = $_SESSION[$this->sessionKey];
        $this->twig->getEnvironment()->addGlobal('csrf_token', $token);

        $method = strtoupper($request->getMethod());
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $path = $request->getUri()->getPath();
            if ($path === '/logout') {
                return $handler->handle($request);
            }

            $body = $request->getParsedBody();
            $bodyToken = is_array($body) ? ($body['_token'] ?? null) : null;
            $headerToken = $request->getHeaderLine('X-CSRF-Token') ?: null;
            $provided = $bodyToken ?? $headerToken;

            if (!$provided || !hash_equals($token, $provided)) {
                $response = new Response();
                $response->getBody()->write('Forbidden - invalid CSRF token.');
                return $response->withStatus(403);
            }
        }

        return $handler->handle($request);
    }
}
