<?php
// src/Service/GoogleSearchConsoleService.php

namespace App\Service;

use Google_Client;
use Google_Service_Webmasters;
use Google_Service_Webmasters_SearchAnalyticsQueryRequest;
use Symfony\Component\HttpFoundation\RequestStack;

class GoogleSearchConsoleService
{
    private $client;
    private $session;

    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();
        $this->client = new Google_Client();
        $this->client->setAuthConfig(__DIR__ . '/../../config/google/credentials.json');
        $this->client->addScope(Google_Service_Webmasters::WEBMASTERS_READONLY);
        $this->client->setRedirectUri('https://justfocus.en.co.uk/callback'); // Remplacez par votre URL
    }

    // Retourner l'URL d'authentification pour Google OAuth2
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    // Authentifier l'utilisateur avec le code d'autorisation
    public function authenticate(string $code): void
    {
        $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($accessToken['error'])) {
            throw new \Exception('Erreur lors de l\'authentification : ' . $accessToken['error_description']);
        }

        // Stocker le jeton d'accès dans la session
        $this->session->set('google_access_token', $accessToken);
        $this->client->setAccessToken($accessToken);
    }

    // Récupérer le client avec un jeton d'accès valide ou le rafraîchir
    public function getClient(): Google_Client
    {
        $accessToken = $this->session->get('google_access_token');

        if ($accessToken) {
            $this->client->setAccessToken($accessToken);

            // Si le jeton d'accès est expiré, le rafraîchir
            if ($this->client->isAccessTokenExpired()) {
                $refreshToken = $this->client->getRefreshToken();
                if ($refreshToken) {
                    $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $this->session->set('google_access_token', $newAccessToken);
                    $this->client->setAccessToken($newAccessToken);
                } else {
                    throw new \Exception('Aucun jeton de rafraîchissement disponible.');
                }
            }
        } else {
            throw new \Exception('Aucun jeton d\'accès disponible.');
        }

        return $this->client;
    }

    // Récupérer les données Search Console
    public function getSearchConsoleData(): array
    {
        $client = $this->getClient();
        $webmasters = new Google_Service_Webmasters($client);
        $siteUrl = 'justfocus.info'; // Remplacez par votre URL

        // Créer une requête Search Analytics
        $request = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
        $request->setStartDate('2023-01-01');
        $request->setEndDate('2023-01-31');
        $request->setDimensions(['query']);

        $response = $webmasters->searchanalytics->query($siteUrl, $request);

        return $response->getRows();
    }
}
