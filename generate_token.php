<?php
require_once __DIR__ . '/vendor/autoload.php';

use Google\Client;

$client = new Client();
$client->setAuthConfig(__DIR__ . '/config/google/credentials.json');
$client->addScope('https://www.googleapis.com/auth/webmasters');
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

// Si tu n'as pas encore un token, fais la demande
if (!file_exists(__DIR__ . '/config/google/token.json')) {
    $authUrl = $client->createAuthUrl();
    printf("Ouvre ce lien dans ton navigateur pour autoriser l'accès à l'API Google Search Console:\n%s\n", $authUrl);
    
    print 'Entre le code de validation: ';
    $authCode = trim(fgets(STDIN));

    // Échange le code d'authentification contre un token d'accès
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    
    // Sauvegarde le token pour un usage futur
    if (!file_exists(__DIR__ . '/config/google')) {
        mkdir(__DIR__ . '/config/google', 0700, true);
    }
    file_put_contents(__DIR__ . '/config/google/token.json', json_encode($accessToken));
    printf("Le token a été sauvegardé dans config/google/token.json\n");
} else {
    echo "Le token d'accès existe déjà dans config/google/token.json\n";
}
