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
use Doctrine\ORM\EntityManagerInterface;

class PostsWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 100;

    private HttpClientInterface $client;
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(HttpClientInterface $client, ArticlesRepository $articlesRepository, CategoryRepository $categoryRepository, UserRepository $userRepository, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/insert/post', name: 'api_posts_wordpress')]
    public function insert()
    {
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
                    return new JsonResponse(['error' => 'Failed to fetch posts from WordPress for category ' . $categoryId], $response->getStatusCode());
                }

                $categoryPosts = $response->toArray();

                if (empty($categoryPosts)) {
                    break;
                }

                foreach ($categoryPosts as $postData) {
                    if ($postData['status'] !== 'publish') {
                        continue;
                    }

                    $existingArticle = $this->articlesRepository->findOneBy(['externalId' => $postData['id']]);

                    if ($existingArticle) {
                        $this->updateArticle($existingArticle, $postData);
                        $posts[] = $existingArticle;
                    } else {
                        $article = $this->createArticle($postData);
                        $posts[] = $article;
                    }
                }

                $page++;
            } while (count($categoryPosts) === self::PER_PAGE);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Articles successfully inserted or updated', 'articles' => $posts]);
    }

    private function updateArticle(Articles $article, array $postData)
    {
        
        $article->setTitle($postData['title']['rendered'] ?? 'No title');
        $article->setSlug($postData['slug'] ?? '');
        $article->setDate(new \DateTime($postData['date'] ?? 'now'));
        $article->setModified(new \DateTime($postData['modified'] ?? 'now'));
        $article->setContent($postData['content']['rendered'] ?? '');
        $article->clearCategories();

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

        $this->entityManager->persist($article);
    }

    private function createArticle(array $postData): Articles
    {
        $article = new Articles();
        $article->setId($postData['id']); // Assuming 'externalId' is a field to store the WP ID
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

        $this->entityManager->persist($article);

        return $article;
    }
}
