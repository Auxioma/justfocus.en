<?php

namespace App\Controller\Api\Insert;

use App\Entity\User;
use App\Entity\Media;
use App\Entity\Articles;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Controller to fetch and insert WordPress posts.
 */
class PostsWordpressController extends AbstractController
{
    /**
     * WordPress API endpoint URL.
     */
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';

    /**
     * HTTP client to make requests.
     */
    private $client;

    /**
     * Entity manager for database operations.
     */
    private $entityManager;

    /**
     * Cache service for caching API responses.
     */
    private $cache;

    /**
     * Constructor to inject dependencies.
     *
     * @param HttpClientInterface    $client         HTTP client service.
     * @param EntityManagerInterface $entityManager  Entity manager service.
     * @param CacheInterface         $cache          Cache service.
     */
    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager, CacheInterface $cache)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
    }

    /**
     * Endpoint to fetch and insert WordPress posts.
     *
     * @return Response JSON response indicating success or failure.
     */
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

    /**
     * Fetch all posts from the WordPress API.
     *
     * @return array Array of posts fetched from WordPress.
     * @throws \Exception If there's an error fetching posts.
     */
    private function getAllPosts(): array
    {
        $allPosts = [];
        $page = 1;
        $perPage = 25;

        do {
            // Fetch posts for the current page
            $posts = $this->getPosts($perPage, $page);
            // Merge current page posts with all posts
            $allPosts = array_merge($allPosts, $posts);
            $page++;
        } while (count($posts) === $perPage); // Continue fetching while full page of posts is returned

        return $allPosts;
    }

    /**
     * Fetch posts from WordPress API for a specific page.
     *
     * @param int $perPage Number of posts per page.
     * @param int $page    Page number to fetch.
     *
     * @return array Array of posts fetched from WordPress.
     * @throws \Exception If there's an error fetching posts.
     */
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

    /**
     * Insert fetched posts into the database.
     *
     * @param array $posts Array of posts fetched from WordPress.
     *
     * @return void
     */
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
                foreach ($post['categories'] as $categoryId) {
                    $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
                    if ($category) {
                        $article->addCategory($category);
                    }
                }
            }

            // Get User
            if (isset($post['author'])) {
                $user = $this->entityManager->getRepository(User::class)->find($post['author']);
                if ($user) {
                    $article->setUser($user);
                }
            }

            // Get the featured image
            if (isset($post['featured_media'])) {
                $featuredMedia = $this->client->request('GET', 'https://justfocus.fr/wp-json/wp/v2/media/' . $post['featured_media']);
                if ($featuredMedia->getStatusCode() === 200) {

                    $media = $featuredMedia->toArray();

                    // Check if the media already exists to avoid duplicates
                    $existingMedia = $this->entityManager->getRepository(Media::class)->find($media['id']);
                    if ($existingMedia) {
                        continue;
                    }
                    
                    // Create new media entity and set its properties
                    $mediaEntity = new Media();
                    $mediaEntity->setId($media['id']);
                    $mediaEntity->setDate(new \DateTime($media['date']));
                    $mediaEntity->setModified(new \DateTime($media['modified']));
                    $mediaEntity->setSlug($media['slug']);
                    $mediaEntity->setGuid($media['guid']['rendered']);
                    $mediaEntity->setTitle($media['title']['rendered']);
                    $mediaEntity->setPost($article);

                    $this->entityManager->persist($mediaEntity);
                }
            }
            
            // Persist the new article entity
            $this->entityManager->persist($article);
        }

        // Flush all persisted entities to the database
        $this->entityManager->flush();
    }
}
