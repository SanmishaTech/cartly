<?php

namespace App\Controllers;

use App\Helpers\HomeSectionConfig;
use App\Models\ShopMetadata;
use Slim\Psr7\Response;
use Slim\Views\Twig;

class HomeController
{
    public function __construct(
        protected Twig $view
    ) {}

    public function index($request, Response $response): Response
    {
        $shop = $request->getAttribute('shop');
        
        // If no shop found (e.g., accessing via localhost), show Cartly landing page
        if (!$shop) {
            return $this->view->render($response, 'home.twig');
        }
        
        $subscription = $request->getAttribute('subscription');
        $subscriptionState = $request->getAttribute('subscriptionState');
        $heroSettings = $shop->hero_settings ?? [];
        if (!is_array($heroSettings)) {
            $heroSettings = json_decode((string)$heroSettings, true);
        }
        if (!is_array($heroSettings)) {
            $heroSettings = [];
        }
        $heroType = (string)($shop->hero_type ?? 'banner');
        $shopMetadata = ShopMetadata::where('shop_id', $shop->id)->first();
        $homeSections = HomeSectionConfig::normalizeSections($shopMetadata?->home_sections ?? []);
        $homeContent = HomeSectionConfig::mergeContent($shopMetadata?->home_content ?? []);

        return $this->view->render($response, 'pages/home.twig', [
            'title' => 'Slim + Twig + Alpine Ecommerce',
            'shop' => $shop,
            'subscription' => $subscription,
            'subscriptionState' => $subscriptionState,
            'hero_type' => $heroType,
            'hero_settings' => $heroSettings,
            'home_sections' => $homeSections,
            'home_content' => $homeContent,
        ]);
    }
}
