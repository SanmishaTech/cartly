<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TrimInputMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            $request = $request->withParsedBody($this->trimArray($body));
        }

        return $handler->handle($request);
    }

    private function trimArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->trimArray($value);
                continue;
            }
            if (is_string($value)) {
                $data[$key] = trim($value);
            }
        }

        return $data;
    }
}
