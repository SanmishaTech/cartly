<?php

namespace App\Controllers;

use Slim\Psr7\Response;

class ThemeAssetController
{
    private string $themesBasePath;

    public function __construct()
    {
        $this->themesBasePath = dirname(__DIR__) . '/Views/themes';
    }

    public function show($request, Response $response): Response
    {
        $theme = (string)$request->getAttribute('theme');
        $path = (string)$request->getAttribute('path');
        $path = ltrim($path, '/');

        if ($theme === '' || $path === '' || str_contains($theme, '..')) {
            return $response->withStatus(404);
        }

        $themeDir = $this->themesBasePath . '/' . $theme . '/assets';
        $fullPath = $themeDir . '/' . $path;
        $realPath = realpath($fullPath);
        $realThemeDir = realpath($themeDir);

        if ($realPath === false || $realThemeDir === false) {
            return $response->withStatus(404);
        }

        if (!str_starts_with($realPath, $realThemeDir) || !is_file($realPath)) {
            return $response->withStatus(404);
        }

        $mimeType = $this->resolveMimeType($realPath);
        $response->getBody()->write((string)file_get_contents($realPath));

        return $response->withHeader('Content-Type', $mimeType);
    }

    private function resolveMimeType(string $path): string
    {
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        if (isset($map[$extension])) {
            return $map[$extension];
        }

        return mime_content_type($path) ?: 'application/octet-stream';
    }
}
