<?php

namespace App\Controller\Api\Insert;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Articles;
use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;

class PostsWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 10;

    private HttpClientInterface $client;
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;

    public function __construct(HttpClientInterface $client, ArticlesRepository $articlesRepository, CategoryRepository $categoryRepository, UserRepository $userRepository)
    {
        $this->client = $client;
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/api/insert/post', name: 'api_posts_wordpress')]
    public function insert()
    {
        // Récupérer toutes les catégories
        $categories = $this->categoryRepository->findAll();

        if (!$categories) {
            return new JsonResponse(['error' => 'No categories found'], 404);
        }

        $posts = [];
        foreach ($categories as $category) {
            $categoryId = $category->getId();

            // Construire l'URL de l'API avec les paramètres nécessaires
            $url = self::WORDPRESS_API_URL . '?categories=' . $categoryId . '&per_page=' . self::PER_PAGE . '&status=publish';

            // Effectuer la requête GET vers l'API WordPress
            $response = $this->client->request('GET', $url);

            // Vérifier si la requête a réussi
            if ($response->getStatusCode() !== 200) {
                return new JsonResponse(['error' => 'Failed to fetch posts from WordPress for category ' . $categoryId], $response->getStatusCode());
            }

            // Décoder la réponse JSON
            $categoryPosts = $response->toArray();

            // Insérer les articles dans la base de données
            foreach ($categoryPosts as $postData) {
                // Vérifier si l'article existe déjà par son identifiant
                $existingArticle = $this->articlesRepository->find($postData['id']);

                if ($existingArticle) {
                    // Mettre à jour l'article existant si nécessaire
                    $existingArticle->setTitle($postData['title']['rendered'] ?? 'No title');
                    $existingArticle->setSlug($postData['slug'] ?? '');
                    $existingArticle->setDate(new \DateTime($postData['date'] ?? 'now'));
                    $existingArticle->setModified(new \DateTime($postData['modified'] ?? 'now'));
                    $existingArticle->setContent($postData['content']['rendered'] ?? '');

                    // Supprimer et réinsérer les catégories (si besoin)
                    $existingArticle->clearCategories();

                    foreach ($postData['categories'] ?? [] as $catId) {
                        $category = $this->categoryRepository->find($catId);
                        if ($category) {
                            $existingArticle->addCategory($category);
                        }
                    }

                    // Mettre à jour l'utilisateur associé
                    $userId = $postData['author'] ?? null;
                    if ($userId) {
                        $user = $this->userRepository->find($userId);
                        if ($user) {
                            $existingArticle->setUser($user);
                        }
                    }

                    // Persister les modifications
                    try {
                        $this->articlesRepository->save($existingArticle);
                        $posts[] = $existingArticle;
                    } catch (\Exception $e) {
                        return new JsonResponse(['error' => 'Failed to update article: ' . $e->getMessage()], 500);
                    }
                } else {
                    // Créer un nouvel article si aucun n'existe avec cet ID
                    $article = new Articles();
                    $article->setId($postData['id']); // Définir l'ID de manière explicite si nécessaire
                    $article->setTitle($postData['title']['rendered'] ?? 'No title');
                    $article->setSlug($postData['slug'] ?? '');
                    $article->setDate(new \DateTime($postData['date'] ?? 'now'));
                    $article->setModified(new \DateTime($postData['modified'] ?? 'now'));
                    $article->setContent($postData['content']['rendered'] ?? '');

                    foreach ($postData['categories'] ?? [] as $catId) {
                        $category = $this->categoryRepository->find($catId);
                        if ($category) {
                            $article->addCategory($category);
                        }
                    }

                    $userId = $postData['author'] ?? null;
                    if ($userId) {
                        $user = $this->userRepository->find($userId);
                        if ($user) {
                            $article->setUser($user);
                        }
                    }

                    // Persister le nouvel article
                    try {
                        $this->articlesRepository->save($article);
                        $posts[] = $article;
                    } catch (\Exception $e) {
                        return new JsonResponse(['error' => 'Failed to save new article: ' . $e->getMessage()], 500);
                    }
                }
            }
        }

        return new JsonResponse(['message' => 'Articles successfully inserted or updated', 'articles' => $posts]);
    }
}
