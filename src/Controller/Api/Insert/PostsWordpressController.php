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
    private const PER_PAGE = 100;

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
        $categories = $this->categoryRepository->findAll();

        if (!$categories) {
            return new JsonResponse(['error' => 'No categories found'], 404);
        }

        $posts = [];
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $url = self::WORDPRESS_API_URL . '?categories=' . $categoryId . '&per_page=' . self::PER_PAGE . '&status=publish';
            $response = $this->client->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return new JsonResponse(['error' => 'Failed to fetch posts from WordPress for category ' . $categoryId], $response->getStatusCode());
            }

            $categoryPosts = $response->toArray();

            foreach ($categoryPosts as $postData) {
                $postData['title']['rendered'] = html_entity_decode($postData['title']['rendered']);
                $postData['content']['rendered'] = html_entity_decode($postData['content']['rendered']);

                $existingArticle = $this->articlesRepository->find($postData['id']);

                if ($existingArticle) {
                    $existingArticle->setTitle($postData['title']['rendered'] ?? 'No title');
                    $existingArticle->setSlug($postData['slug'] ?? '');
                    $existingArticle->setDate(new \DateTime($postData['date'] ?? 'now'));
                    $existingArticle->setModified(new \DateTime($postData['modified'] ?? 'now'));
                    $existingArticle->setContent($postData['content']['rendered'] ?? '');

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

                    try {
                        $this->articlesRepository->save($existingArticle);
                        $posts[] = $existingArticle;
                    } catch (\Exception $e) {
                        return new JsonResponse(['error' => 'Failed to update article: ' . $e->getMessage()], 500);
                    }
                } else {
                    $article = new Articles();
                    $article->setId($postData['id']);
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