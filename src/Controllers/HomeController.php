<?php

namespace App\Controllers;

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
        $heroSettings = json_decode((string)($shop->hero_settings ?? ''), true);
        if (!is_array($heroSettings)) {
            $heroSettings = [];
        }
        $heroType = (string)($shop->hero_type ?? 'carousel');

        return $this->view->render($response, 'pages/home.twig', [
            'title' => 'Slim + Twig + Alpine Ecommerce',
            'shop' => $shop,
            'subscription' => $subscription,
            'subscriptionState' => $subscriptionState,
            'hero_type' => $heroType,
            'hero_settings' => $heroSettings,
        ]);
    }
}
