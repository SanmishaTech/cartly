<?php

namespace App\Middleware;

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
    
    public function __construct(Twig $twig, ThemeResolver $themeResolver)
    {
        $this->twig = $twig;
        $this->themeResolver = $themeResolver;
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
}
