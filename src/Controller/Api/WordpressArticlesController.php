<?php

namespace App\Controller\Api;

use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Articles;

class WordpressArticlesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ){}
    
    #[Route('/api/wordpress/articles', name: 'app_api_wordpress_articles')]
    public function index(): Response
    {
        // Initialisation du client Guzzle pour effectuer des requêtes HTTP
        $client = new Client(['base_uri' => 'https://justfocus.fr/wp-json/wp/v2/']);
        
        // Tableau pour stocker tous les articles récupérés
        $allArticles = [];

        // Pagination initiale
        $page = 1;
        $perPage = 50; // Nombre d'articles à récupérer par page

        do {
            try {
                // Requête GET pour récupérer les articles paginés
                $response = $client->request('GET', 'posts?lang=fr', [
                    'query' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        // Ajoutez d'autres paramètres si nécessaire
                    ],
                ]);

                // Décodage de la réponse JSON en tableau PHP
                $articles = json_decode($response->getBody()->getContents(), true);

                // Vérifier si des articles ont été récupérés
                if (!empty($articles)) {
                    $allArticles = array_merge($allArticles, $articles);
                } else {
                    // Arrêter la boucle si aucun article n'est récupéré
                    break;
                }

                // Passer à la page suivante
                $page++;

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                // Gérer les erreurs de requête HTTP ici
                $response = $e->getResponse();
                if ($response && $response->getStatusCode() == 400) {
                    // Arrêter la boucle si une erreur 400 (Bad Request) est détectée
                    break;
                }
            }
        } while (!empty($articles));

        // Vérifiez les articles récupérés (à supprimer après vérification)
        dd($allArticles);

        // Parcourir les articles récupérés et les sauvegarder en base de données
        foreach ($allArticles as $articleData) {
            $newArticle = new Articles();
            $newArticle->setId($articleData['id']);
            $newArticle->setTitle($articleData['title']['rendered']);
            $newArticle->setSlug($articleData['slug']);
            $newArticle->setDate(new \DateTime($articleData['date']));
            $newArticle->setModified(new \DateTime($articleData['modified']));
            $newArticle->setContent($articleData['content']['rendered']);

            // Enregistrer l'article en base de données
            $this->entityManager->persist($newArticle);
        }

        // Exécuter les requêtes SQL pour enregistrer les articles
        $this->entityManager->flush();

        // Réponse HTTP 200 OK avec un message de succès
        return new Response('Articles imported successfully.');
    }
}
