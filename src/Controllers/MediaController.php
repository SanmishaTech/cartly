<?php

namespace App\Controllers;

use Slim\Psr7\Response;

class MediaController
{
    private string $basePath;

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2) . '/storage/uploads';
    }

    public function show($request, Response $response): Response
    {
        $path = (string)$request->getAttribute('path');
        $path = ltrim($path, '/');
        $fullPath = $this->basePath . '/' . $path;
        if (!is_file($fullPath)) {
            $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
            if ($webpPath !== $path) {
                $path = $webpPath;
                $fullPath = $this->basePath . '/' . $path;
            }
        }

        $realPath = realpath($fullPath);

        if ($realPath === false || !str_starts_with($realPath, $this->basePath) || !is_file($realPath)) {
            return $response->withStatus(404);
        }

        $mimeType = mime_content_type($realPath) ?: 'application/octet-stream';
        $response->getBody()->write((string)file_get_contents($realPath));

        return $response->withHeader('Content-Type', $mimeType);
    }
}
