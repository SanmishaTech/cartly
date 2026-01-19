<?php

namespace App\Controllers;

use App\Models\Package;
use Slim\Psr7\Response;

class RootController extends AppController
{
    /**
     * Root dashboard
     */
    public function dashboard($request, Response $response): Response
    {
        $this->startSession();
        
        $packages = Package::all();

        return $this->render($response, 'dashboard/root.twig', [
            'session' => $_SESSION,
            'packages' => $packages,
        ]);
    }
}
