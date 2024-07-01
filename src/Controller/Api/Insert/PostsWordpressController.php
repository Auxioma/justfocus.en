<?php

namespace App\Controller\Api\Insert;

use App\Entity\User;
use App\Entity\Articles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PostsWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private $client;
    private $entityManager;
    private $cache;

    // Constructor to inject dependencies
    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager, CacheInterface $cache)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    // Route to fetch and insert WordPress posts
    #[Route('/api/wordpress/posts', name: 'app_api_posts')]
    public function index(): Response
    {
        try {
            // Get posts from cache or fetch from WordPress API if not cached
            $posts = $this->cache->get('wordpress_posts', function (ItemInterface $item) {
                $item->expiresAfter(86400); // Cache for 1 day
                return $this->getAllPosts();
            });

            // Insert posts into the database
            $this->insertPosts($posts);
            return new JsonResponse(['status' => 'success', 'message' => 'Posts inserted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // Method to get posts from WordPress API
    private function getPosts(int $perPage = 100, int $page = 1): array
    {
        $response = $this->client->request('GET', self::WORDPRESS_API_URL, [
            'query' => [
                'per_page' => $perPage,
                'page' => $page,
            ],
        ]);

        // Throw exception if the response status code is not 200 (OK)
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error fetching posts');
        }

        return $response->toArray();
    }

    // Method to fetch all posts from WordPress API
    private function getAllPosts(): array
    {
        $allPosts = [];
        $page = 1;
        $perPage = 100;

        do {
            // Fetch posts for the current page
            $posts = $this->getPosts($perPage, $page);
            // Merge current page posts with all posts
            $allPosts = array_merge($allPosts, $posts);
            $page++;
        } while (count($posts) === $perPage); // Continue fetching while full page of posts is returned

        return $allPosts;
    }

    // Method to insert fetched posts into the database
    private function insertPosts(array $posts): void
    {
        foreach ($posts as $post) {
            // Check if the post already exists to avoid duplicates
            $existingArticle = $this->entityManager->getRepository(Articles::class)->find($post['id']);
            if ($existingArticle) {
                continue;
            }

            // Create new article entity and set its properties
            $article = new Articles();
            $article->setId($post['id']);
            $article->setTitle($post['title']['rendered']);
            $article->setSlug($post['slug']);
            $article->setDate(new \DateTime($post['date']));
            $article->setModified(new \DateTime($post['modified']));
            $article->setContent($post['content']['rendered']);

            // Get the categories
            if (isset($post['categories'])) {
                foreach ($post['categories'] as $category) {
                    $article->addCategory($category);
                }
            }

            // Get User
            if (isset($post['author'])) {
                $user = $this->entityManager->getRepository(User::class)->find($post['author']);
                if ($user) {
                    $article->setUser($user);
                }
            }
            
            // Persist the new article entity
            $this->entityManager->persist($article);
        }

        // Flush all persisted entities to the database
        $this->entityManager->flush();
    }
}
