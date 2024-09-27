<?php

// src/Service/GoogleSearchConsoleService.php
namespace App\Service;

use Google\Client;
use Google\Service\SearchConsole;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GoogleSearchConsoleService
{
    private $client;
    private $searchConsole;
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;

        // Initialisation du client Google
        $this->client = new Client();
        $this->client->setAuthConfig($this->params->get('kernel.project_dir') . '/config/google/credentials.json');
        $this->client->addScope(SearchConsole::WEBMASTERS_READONLY);

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
                echo 'Ouvrez ce lien dans votre navigateur pour authentifier : ' . $authUrl;
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






