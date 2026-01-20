<?php

namespace App\Middleware;

use App\Config\FontConfig;
use App\Models\Shop;
use App\Services\SeoService;
use App\Services\ThemeResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

/**
 * ThemeMiddleware
 * 
 * Configures the theme system based on the current route/context
 * Must run after ShopResolverMiddleware
 */
class ThemeMiddleware implements MiddlewareInterface
{
    private Twig $twig;
    private ThemeResolver $themeResolver;
    private SeoService $seoService;
    
    public function __construct(Twig $twig, ThemeResolver $themeResolver, SeoService $seoService)
    {
        $this->twig = $twig;
        $this->themeResolver = $themeResolver;
        $this->seoService = $seoService;
    }
    
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Determine context from route
        $path = $request->getUri()->getPath();
        $context = $this->resolveContext($path);
        
        // Set context in theme resolver
        $this->themeResolver->setContext($context);
        
        // Get shop from request attributes (set by ShopResolverMiddleware)
        $shop = $request->getAttribute('shop');
        if ($shop) {
            $this->themeResolver->setShop($shop);
        }
        
        // Reconfigure Twig loader based on context (always, not just when shop exists)
        $environment = $this->twig->getEnvironment();
        $paths = $this->themeResolver->resolveThemePaths();
        
        // Create new loader with context-specific paths (filter out non-existent paths)
        $loader = new \Twig\Loader\FilesystemLoader();
        foreach ($paths as $namespace => $namespacePaths) {
            // Filter out non-existent paths to avoid Twig errors
            $validPaths = array_filter(
                $namespacePaths,
                fn($path) => is_dir($path)
            );
            
            if (empty($validPaths)) {
                continue;
            }
            
            if ($namespace === \Twig\Loader\FilesystemLoader::MAIN_NAMESPACE) {
                $loader->setPaths($validPaths);
            } else {
                $loader->setPaths($validPaths, $namespace);
            }
        }
        $environment->setLoader($loader);
        
        // Add theme resolver and request to Twig environment for use in views
        $environment->addGlobal('theme', $this->themeResolver);
        $environment->addGlobal('request', $request);
        $environment->addGlobal('current_path', $path);
        $environment->addGlobal('seo', $this->buildSeoPayload($context, $shop, $request));
        $themeConfig = $this->buildThemeConfig($shop);
        $environment->addGlobal('theme_config', $themeConfig);
        $environment->addGlobal('font_profiles', $this->resolveFontProfiles($themeConfig));
        
        // Add context to request for controllers
        $request = $request->withAttribute('context', $context);
        
        return $handler->handle($request);
    }
    
    /**
     * Determine context from request path
     * 
     * Admin context includes:
     * - /admin/* (admin dashboard and management)
     * - /login (unified login page)
     * Landing context:
     * - Root domain without shop (localhost)
     */
    private function resolveContext(string $path): string
    {
        // All admin routes including auth
        if (str_starts_with($path, '/admin') 
            || $path === '/login'
            || str_starts_with($path, '/login/')) {
            return 'admin';
        }
        
        // Everything else is storefront (customer-facing)
        return 'storefront';
    }

    private function buildSeoPayload(string $context, mixed $shop, ServerRequestInterface $request): ?array
    {
        if ($context !== 'storefront' || !$shop) {
            return null;
        }

        return $this->seoService->buildForShop($shop, $request);
    }

    private function buildThemeConfig(?Shop $shop): array
    {
        $themeName = $shop?->theme ?: 'default';
        $defaults = $this->themeResolver->getThemeMetadata($themeName) ?? [];
        $overrides = $this->decodeThemeConfig($shop?->theme_config ?? null);

        if (empty($overrides)) {
            return $defaults ?: [];
        }

        if (empty($defaults)) {
            return $overrides;
        }

        return $this->mergeThemeConfig($defaults, $overrides);
    }

    private function decodeThemeConfig(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function mergeThemeConfig(array $defaults, array $overrides): array
    {
        return array_replace_recursive($defaults, $overrides);
    }

    private function resolveFontProfiles(array $themeConfig): array
    {
        $defaultKey = 'system';
        if (isset($themeConfig['font_profile']) && is_string($themeConfig['font_profile'])) {
            $defaultKey = $themeConfig['font_profile'];
        }

        $baseKey = $defaultKey;
        $headingKey = $defaultKey;

        if (isset($themeConfig['font_profile_base']) && is_string($themeConfig['font_profile_base'])) {
            $baseKey = $themeConfig['font_profile_base'];
        }
        if (isset($themeConfig['font_profile_heading']) && is_string($themeConfig['font_profile_heading'])) {
            $headingKey = $themeConfig['font_profile_heading'];
        }

        return [
            'base' => $this->decorateProfile($baseKey),
            'heading' => $this->decorateProfile($headingKey),
        ];
    }

    private function decorateProfile(string $key): array
    {
        $profile = FontConfig::getProfile($key);
        $profile['key'] = $key;
        return $profile;
    }
}
