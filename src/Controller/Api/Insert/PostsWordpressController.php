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
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

class PostsWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 5;

    private HttpClientInterface $client;
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        HttpClientInterface $client,
        ArticlesRepository $articlesRepository,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        $this->client = $client;
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
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
            $page = 1;

            do {
                $url = self::WORDPRESS_API_URL . '?categories=' . $categoryId . '&per_page=' . self::PER_PAGE . '&page=' . $page . '&status=publish';
                $response = $this->client->request('GET', $url);

                if ($response->getStatusCode() !== 200) {
                    $this->logger->error('Failed to fetch posts from WordPress for category ' . $categoryId);
                    break;
                }

                $categoryPosts = $response->toArray();
                if (empty($categoryPosts)) {
                    break;
                }

                $this->entityManager->beginTransaction();
                try {
                    foreach ($categoryPosts as $postData) {
                        // Vérifier si l'article existe déjà par son identifiant
                        $existingArticle = $this->articlesRepository->find($postData['id']);

                        if ($existingArticle) {
                            // Mettre à jour l'article existant si nécessaire
                            $existingArticle->setTitle(html_entity_decode($postData['title']['rendered'] ?? 'No title'));
                            $existingArticle->setSlug(html_entity_decode($postData['slug'] ?? ''));
                            $existingArticle->setDate(new \DateTime($postData['date'] ?? 'now'));
                            $existingArticle->setModified(new \DateTime($postData['modified'] ?? 'now'));
                            $existingArticle->setContent(html_entity_decode($postData['content']['rendered'] ?? ''));

                            $existingArticle->clearCategories();
                            foreach ($postData['categories'] ?? [] as $catId) {
                                $category = $this->categoryRepository->find($catId);
                                if ($category) {
                                    $existingArticle->addCategory($category);
                                }
                            }

                            $userId = $postData['author'] ?? null;
                            if ($userId) {
                                $user = $this->userRepository->find($userId);
                                if ($user) {
                                    $existingArticle->setUser($user);
                                }
                            }

                            $this->articlesRepository->save($existingArticle);
                            $posts[] = $existingArticle;
                        } else {
                            // Créer un nouvel article si aucun n'existe avec cet ID
                            $article = new Articles();
                            $article->setId($postData['id']); // Définir l'ID de manière explicite si nécessaire
                            $article->setTitle(html_entity_decode($postData['title']['rendered'] ?? 'No title'));
                            $article->setSlug(html_entity_decode($postData['slug'] ?? ''));
                            $article->setDate(new \DateTime($postData['date'] ?? 'now'));
                            $article->setModified(new \DateTime($postData['modified'] ?? 'now'));
                            $article->setContent(html_entity_decode($postData['content']['rendered'] ?? ''));

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

                            $this->articlesRepository->save($article);
                            $posts[] = $article;
                        }
                    }
                    $this->entityManager->commit();
                } catch (\Exception $e) {
                    $this->entityManager->rollback();
                    $this->logger->error('Failed to process posts: ' . $e->getMessage());
                    return new JsonResponse(['error' => 'Failed to process posts: ' . $e->getMessage()], 500);
                }

                $page++;
            } while (count($categoryPosts) === self::PER_PAGE);
        }

        return new JsonResponse(['message' => 'Articles successfully inserted or updated', 'articles' => $posts]);
    }
}

