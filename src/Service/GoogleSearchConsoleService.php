<?php

namespace App\Service;

use Google\Client;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;

class GoogleSearchConsoleService
{
    private $client;
    private $service;

    public function __construct()
    {
        // Créer un client Google API
        $this->client = new Client();
        $this->client->setApplicationName('Symfony Google Search Console');
        
        // Spécifie le chemin vers ton fichier de credentials OAuth
        $this->client->setAuthConfig(__DIR__ . '/../../config/google/credentials.json');
        
        // Définir les autorisations nécessaires pour l'API Search Console
        $this->client->addScope(SearchConsole::WEBMASTERS_READONLY);
        
        // Activer le mode hors connexion pour pouvoir rafraîchir les tokens
        $this->client->setAccessType('offline');
        
        // Initialise le service Google Search Console
        $this->service = new SearchConsole($this->client);
    }

    // Méthode pour récupérer des données d'analyse de la Search Console
    public function getSearchAnalytics(string $siteUrl, string $startDate, string $endDate)
    {
        // Créer une instance de SearchAnalyticsQueryRequest
        $request = new SearchAnalyticsQueryRequest();
        
        // Définir les paramètres de la requête
        $request->setStartDate($startDate);
        $request->setEndDate($endDate);
        $request->setDimensions(['query']);
        
        // Appeler l'API Search Console avec l'objet request
        $query = $this->service->searchanalytics->query($siteUrl, $request);

        return $query->getRows();
    }
}
