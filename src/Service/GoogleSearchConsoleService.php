<?php

// src/Service/GoogleSearchConsoleService.php
namespace App\Service;

use Google\Client;
use Google\Service\SearchConsole;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class GoogleSearchConsoleService
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
        $this->client->setAuthConfig($this->params->get('kernel.project_dir') . '/config/google/credentials.json');
        $this->client->addScope(SearchConsole::WEBMASTERS_READONLY);

        // Définir l'URI de redirection
        $currentRequest = $this->requestStack->getCurrentRequest();
        $redirectUri = $currentRequest->getSchemeAndHttpHost() . '/callback';  // par ex., http://localhost:8000/callback
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
                header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                exit;
            }

            // Sauvegarder le nouveau token
            file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
        }

        $this->searchConsole = new SearchConsole($this->client);
    }

    public function getSitesList()
    {
        return $this->searchConsole->sites->listSites();
    }

    public function getSiteData($siteUrl)
    {
        return $this->searchConsole->searchanalytics->query($siteUrl, [
            'startDate' => '2023-01-01',
            'endDate' => '2023-12-31',
            'dimensions' => ['query'],
            'rowLimit' => 10,
        ]);
    }
}

