<?php

namespace App\Controller\Google;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Google\Client;
use Google\Service\SearchConsole;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class GoogleCallbackController extends AbstractController
{
    private $client;
    private $searchConsole;
    private $params;
    private $requestStack;

    public function __construct(ParameterBagInterface $params, RequestStack $requestStack)
    {
        $this->params = $params;
        $this->requestStack = $requestStack;

        // Initialisation du client Google
        $this->client = new Client();
        $credentialsPath = $this->params->get('kernel.project_dir') . '/config/google/credentials.json';

        // Vérifiez si le fichier credentials existe et est lisible
        if (!file_exists($credentialsPath) || !is_readable($credentialsPath)) {
            throw new \Exception('Le fichier credentials.json est introuvable ou illisible.');
        }

        $this->client->setAuthConfig($credentialsPath);
        $this->client->addScope(SearchConsole::WEBMASTERS_READONLY);

        // Définir l'URI de redirection
        $currentRequest = $this->requestStack->getCurrentRequest();
        $redirectUri = $currentRequest->getSchemeAndHttpHost() . '/callback';

        if (!$redirectUri) {
            throw new \Exception('L\'URI de redirection est mal configurée. Vérifiez votre Google Cloud Console.');
        }

        $this->client->setRedirectUri($redirectUri);

        // Vérification si un token d'accès existe déjà
        $tokenPath = $this->params->get('kernel.project_dir') . '/config/google/token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($accessToken);
        }

        // Si le token a expiré, on le rafraîchit
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            } else {
                // Rediriger l'utilisateur pour obtenir un nouveau token
                $authUrl = $this->client->createAuthUrl();
                
                if (!$authUrl) {
                    throw new \Exception('Impossible de générer l\'URL d\'authentification. Vérifiez les paramètres OAuth.');
                }

                header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                exit;
            }

            // Sauvegarder le nouveau token
            file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
        }

        // Initialisation du service Search Console
        $this->searchConsole = new SearchConsole($this->client);
    }
    
    #[Route('/callback', name: 'google_callback', methods: ['GET'], priority: 10)]
    public function callback(Request $request, SessionInterface $session): RedirectResponse
    {
        // Vérifie la présence du paramètre 'code' dans la requête de callback
        $code = $request->query->get('code');
        if ($code) {
            // Échange le code d'autorisation contre un token d'accès
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($accessToken['error'])) {
                throw new \Exception('Erreur lors de la récupération du token: ' . $accessToken['error']);
            }

            // Sauvegarde le token dans un fichier
            $tokenPath = $this->params->get('kernel.project_dir') . '/config/google/token.json';
            file_put_contents($tokenPath, json_encode($accessToken));

            // Rediriger vers l'interface d'administration après l'authentification réussie
            return $this->redirectToRoute('admin');
        }

        // Si aucun code d'autorisation n'est présent, rediriger vers une erreur ou une autre action
        throw new \Exception('Code d\'autorisation manquant dans la requête Google.');
    }
}