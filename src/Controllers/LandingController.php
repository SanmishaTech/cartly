<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

/**
 * LandingController
 * 
 * Handles the public landing page at /
 * This is the marketing page that promotes Cartly product
 * Does NOT require shop resolver (public page)
 */
class LandingController
{
    private Twig $twig;
    
    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }
    
    /**
     * Show landing page
     */
    public function index(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'pages/home.twig');
    }
}
