<?php

namespace App\Controller\Api;

use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\Articles;
use App\Entity\Category;

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
        $perPage = 5; // Nombre d'articles à récupérer par page

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

        foreach ($allArticles as $article) {
            $articleEntity = $this->entityManager
                                ->getRepository(Articles::class)
                                ->findOneBy(['id' => $article['id']]);

            // verification si les modified date sont differentes
            if ($articleEntity->getModified() != new \DateTime($article['modified'])) {
                echo 'Article already up to date' . $articleEntity->getId();
                // modification de l'article
                $title = html_entity_decode($article['title']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content = html_entity_decode($article['content']['rendered'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
                $articleEntity->setTitle($title)
                              ->setSlug($article['slug'])
                              ->setDate(new \DateTime($article['date']))
                              ->setModified(new \DateTime($article['modified']))
                              ->setContent($content);

                // Ajouter les catégories
                $articleEntity->clearCategories(); // Suppression des anciennes catégories
                foreach ($article['categories'] as $categoryId) {
                    $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
                    if ($category) {
                        $articleEntity->addCategory($category);
                    }
                }

                $this->entityManager->persist($articleEntity);
            }

            $this->entityManager->flush();
        }

        // Réponse HTTP 200 OK avec un message de succès
        return new Response('Articles imported successfully.');
    }
}
