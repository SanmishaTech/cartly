<?php

namespace App\Controllers;

use App\Models\Page;
use App\Services\SeoService;
use Slim\Psr7\Response;
use Slim\Views\Twig;

class PageController
{
    public function __construct(
        protected Twig $view,
        protected SeoService $seoService
    ) {}

    public function show($request, Response $response, array $args): Response
    {
        $shop = $request->getAttribute('shop');
        if (!$shop) {
            return $this->view->render($response, 'home.twig');
        }

        $slug = trim((string)($args['slug'] ?? ''));
        if ($slug === '' || $this->isReservedSlug($slug)) {
            return $response->withStatus(404);
        }

        $page = Page::where('shop_id', $shop->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('type', 'standard')
            ->first();
        if (!$page) {
            return $response->withStatus(404);
        }

        $blocks = $page->content_json ?? [];
        if (!is_array($blocks)) {
            $blocks = [];
        }

        $seo = $this->seoService->buildForPage($page, $request);

        return $this->view->render($response, 'pages/page.twig', [
            'shop' => $shop,
            'page' => $page,
            'blocks' => $blocks,
            'seo' => $seo,
        ]);
    }

    private function isReservedSlug(string $slug): bool
    {
        $reserved = [
            'admin',
            'login',
            'logout',
            'media',
            'assets',
            'products',
            'categories',
            'cart',
            'checkout',
            'account',
        ];
        return in_array($slug, $reserved, true);
    }
}
