<?php

namespace App\Controller\Google;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Service\GoogleSearchConsoleService;
use Symfony\Component\Routing\Attribute\Route;

class GoogleCallbackController extends AbstractController
{
    #[Route('/callback', name: 'google_callback', methods: ['GET'], priority: 10)]
    public function callback(Request $request, GoogleSearchConsoleService $googleService): Response
    {
        // Récupérer le code dans l'URL de callback
        $code = $request->get('code');
        
        if ($code) {
            // Échanger le code contre un token d'accès
            $accessToken = $googleService->getClient()->fetchAccessTokenWithAuthCode($code);

            // Sauvegarder le token pour les futures requêtes
            file_put_contents($this->getParameter('kernel.project_dir') . '/config/google/token.json', json_encode($accessToken));

            return $this->redirectToRoute('admin'); // Rediriger vers la page admin ou une autre page de votre choix
        }

        return new Response('Erreur lors de l\'authentification.', Response::HTTP_FORBIDDEN);
    }

}