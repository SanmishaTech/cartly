<?php

namespace App\Services;

use App\Models\Shop;
use Twig\Loader\FilesystemLoader;

/**
 * ThemeResolver
 * 
 * Resolves the correct theme paths for Twig based on context:
 * - Storefront: Uses shop's selected theme with fallback to default (multi-theme)
 * - Admin: Single shared admin theme (used by shop owner + root admin)
 * 
 * Note: Auth views are inside admin folder since they're for admin access
 */
class ThemeResolver
{
    private const DEFAULT_STOREFRONT_THEME = 'default';
    private const ADMIN_CONTEXT = 'admin';
    private const STOREFRONT_CONTEXT = 'storefront';
    private const LANDING_CONTEXT = 'landing';
    
    private string $basePath;
    private ?Shop $currentShop = null;
    private string $context = self::STOREFRONT_CONTEXT;
    
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }
    
    /**
     * Set the current shop (for storefront context)
     */
    public function setShop(?Shop $shop): self
    {
        $this->currentShop = $shop;
        return $this;
    }
    
    /**
     * Set the current context (storefront, admin, auth)
     */
    public function setContext(string $context): self
    {
        $this->context = $context;
        return $this;
    }
    
    /**
     * Get the current context
     */
    public function getContext(): string
    {
        return $this->context;
    }
    
    /**
     * Configure Twig loader with appropriate theme paths
     */
    public function configureLoader(FilesystemLoader $loader): void
    {
        $paths = $this->resolveThemePaths();
        
        // Clear existing paths
        foreach ($loader->getNamespaces() as $namespace) {
            $loader->setPaths([], $namespace);
        }
        
        // Set new paths with proper namespaces
        foreach ($paths as $namespace => $namespacePaths) {
            if ($namespace === FilesystemLoader::MAIN_NAMESPACE) {
                $loader->setPaths($namespacePaths);
            } else {
                $loader->setPaths($namespacePaths, $namespace);
            }
        }
    }
    
    /**
     * Resolve theme paths based on current context
     */
    public function resolveThemePaths(): array
    {
        // Check if this is a landing page (no shop + no admin context)
        if (!$this->currentShop && $this->context === self::STOREFRONT_CONTEXT) {
            return $this->getLandingPaths();
        }
        
        switch ($this->context) {
            case self::ADMIN_CONTEXT:
                return $this->getAdminPaths();
            
            case self::STOREFRONT_CONTEXT:
            default:
                return $this->getStorefrontPaths();
        }
    }
    
    /**
     * Get landing page paths (public landing page without shop)
     */
    private function getLandingPaths(): array
    {
        return [
            FilesystemLoader::MAIN_NAMESPACE => [
                "{$this->basePath}/core/landing",
                "{$this->basePath}/core/partials",
            ],
            'landing' => [
                "{$this->basePath}/core/landing",
            ],
        ];
    }
    
    /**
     * Get storefront theme paths with theme support
     * Resolution: active theme -> default theme -> core (fallback)
     */
    private function getStorefrontPaths(): array
    {
        $theme = $this->getCurrentTheme();
        
        $mainPaths = [];
        
        // Add active theme if not default
        if ($theme !== self::DEFAULT_STOREFRONT_THEME) {
            $mainPaths[] = "{$this->basePath}/themes/{$theme}";
            $mainPaths[] = "{$this->basePath}/themes/{$theme}/pages";
            $mainPaths[] = "{$this->basePath}/themes/{$theme}/partials";
        }
        
        // Add default theme (fallback)
        $mainPaths[] = "{$this->basePath}/themes/default";
        $mainPaths[] = "{$this->basePath}/themes/default/pages";
        $mainPaths[] = "{$this->basePath}/themes/default/partials";
        
        // Add core as final fallback
        $mainPaths[] = "{$this->basePath}/core";
        $mainPaths[] = "{$this->basePath}/core/partials";
        
        $paths = [
            FilesystemLoader::MAIN_NAMESPACE => $mainPaths,
            'theme' => [
                "{$this->basePath}/themes/{$theme}",
            ],
            'default' => [
                "{$this->basePath}/themes/default",
            ],
            'core' => [
                "{$this->basePath}/core",
            ],
        ];
        
        return $paths;
    }
    
    /**
     * Get admin theme paths
     * Used by both shop owners and root admin
     */
    private function getAdminPaths(): array
    {
        return [
            FilesystemLoader::MAIN_NAMESPACE => [
                "{$this->basePath}/core/admin",
                "{$this->basePath}/core/auth",
            ],
            'admin' => [
                "{$this->basePath}/core/admin",
            ],
            'auth' => [
                "{$this->basePath}/core/auth",
            ],
        ];
    }
    
    /**
     * Get the current theme name
     * Falls back to default if the requested theme doesn't exist
     */
    private function getCurrentTheme(): string
    {
        if ($this->currentShop && $this->currentShop->theme) {
            $themeName = $this->currentShop->theme;
            
            // Verify the theme directory exists, otherwise fall back to default
            $themePath = "{$this->basePath}/themes/{$themeName}";
            if (is_dir($themePath) && $this->isValidTheme($themePath)) {
                return $themeName;
            }
        }
        
        return self::DEFAULT_STOREFRONT_THEME;
    }
    
    /**
     * Get available themes by scanning the themes directory
     */
    public function getAvailableThemes(): array
    {
        $themesPath = $this->basePath . '/themes';
        
        if (!is_dir($themesPath)) {
            return [self::DEFAULT_STOREFRONT_THEME];
        }
        
        $themes = [];
        $directories = scandir($themesPath);
        
        foreach ($directories as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir("{$themesPath}/{$dir}")) {
                continue;
            }
            
            // Check if theme has required structure
            if ($this->isValidTheme("{$themesPath}/{$dir}")) {
                $themes[] = $dir;
            }
        }
        
        return $themes ?: [self::DEFAULT_STOREFRONT_THEME];
    }
    
    /**
     * Check if a theme directory has the required structure
     */
    private function isValidTheme(string $themePath): bool
    {
        // A valid theme must have at least layout.twig file
        return file_exists("{$themePath}/layout.twig");
    }
    
    /**
     * Get theme metadata (if theme.json exists)
     */
    public function getThemeMetadata(string $themeName): ?array
    {
        $metadataPath = $this->basePath . "/themes/{$themeName}/theme.json";
        
        if (!file_exists($metadataPath)) {
            // Fall back to default theme metadata
            $metadataPath = $this->basePath . "/themes/default/theme.json";
        }
        
        if (!file_exists($metadataPath)) {
            return null;
        }
        
        $content = file_get_contents($metadataPath);
        return json_decode($content, true);
    }
    
    /**
     * Get the asset path for current theme
     */
    public function getThemeAssetPath(string $asset = ''): string
    {
        $theme = $this->getCurrentTheme();
        $basePath = "/assets/themes/{$theme}";
        
        return $asset ? "{$basePath}/{$asset}" : $basePath;
    }
}
