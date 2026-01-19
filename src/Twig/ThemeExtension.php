<?php

namespace App\Twig;

use App\Services\ThemeResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * ThemeExtension
 * 
 * Provides Twig functions for theme-related operations
 */
class ThemeExtension extends AbstractExtension
{
    private ThemeResolver $themeResolver;
    
    public function __construct(ThemeResolver $themeResolver)
    {
        $this->themeResolver = $themeResolver;
    }
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('theme_asset', [$this, 'themeAsset']),
            new TwigFunction('theme_name', [$this, 'themeName']),
            new TwigFunction('is_admin', [$this, 'isAdmin']),
            new TwigFunction('is_storefront', [$this, 'isStorefront']),
            new TwigFunction('current_path', [$this, 'currentPath']),
            new TwigFunction('is_active_path', [$this, 'isActivePath']),
        ];
    }
    
    /**
     * Generate asset URL for current theme
     */
    public function themeAsset(string $path): string
    {
        return $this->themeResolver->getThemeAssetPath($path);
    }
    
    /**
     * Get current theme name
     */
    public function themeName(): string
    {
        return $this->themeResolver->getContext();
    }
    
    /**
     * Check if current context is admin
     */
    public function isAdmin(): bool
    {
        return $this->themeResolver->getContext() === 'admin';
    }
    
    /**
     * Check if current context is storefront
     */
    public function isStorefront(): bool
    {
        return $this->themeResolver->getContext() === 'storefront';
    }

    public function currentPath(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);
        return $path ?: '/';
    }

    /**
     * Check if current path matches the given path or prefix
     */
    public function isActivePath(string $path, array $aliases = []): bool
    {
        $current = $this->currentPath();
        if ($current === $path) {
            return true;
        }
        foreach ($aliases as $alias) {
            if ($current === $alias) {
                return true;
            }
        }
        return $path !== '/' && str_starts_with($current, rtrim($path, '/') . '/');
    }
}
