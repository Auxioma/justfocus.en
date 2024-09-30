<?php

// src/Controller/GoogleAuthController.php

namespace App\Controller;

use App\Service\GoogleSearchConsoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleAuthController extends AbstractController
{
    private $googleService;

    public function __construct(GoogleSearchConsoleService $googleService)
    {
        $this->googleService = $googleService;
    }

    #[Route('/callback', name: 'google_callback', priority: 10)]
    public function handleGoogleCallback(Request $request): Response
    {
        $code = $request->get('code');
        if ($code) {
            // Authentifier l'utilisateur avec le code reçu de Google
            $this->googleService->authenticate($code);
        }

        // Rediriger vers le tableau de bord après authentification
        return $this->redirectToRoute('admin');
    }
}
