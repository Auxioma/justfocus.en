<?php

namespace App\Controller\Api\Insert;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller to synchronize categories from the WordPress API to the local database.
 */
class CategoryWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/categories?per_page=100&lang=fr';

    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    /**
     * Constructor to inject dependencies.
     *
     * @param EntityManagerInterface $entityManager
     * @param HttpClientInterface    $httpClient
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Endpoint to synchronize categories.
     *
     * @return Response JSON response indicating success or failure.
     */
    #[Route('/api/wordpress/category', name: 'app_api_category')]
    public function index(): Response
    {
        try {
            $categories = $this->fetchCategories();
            $this->persistCategories($categories);
            return new JsonResponse(['success' => 'Categories synchronized successfully.']);
        } catch (\Exception $e) {
            $this->logger->error('Error during category synchronization: ' . $e->getMessage());
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Fetch categories from the WordPress API.
     *
     * @return array Array of categories fetched from WordPress.
     * @throws \Exception If there's an error fetching categories.
     */
    private function fetchCategories(): array
    {
        $response = $this->httpClient->request('GET', self::WORDPRESS_API_URL);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error fetching categories: ' . $response->getStatusCode());
        }

        return $response->toArray();
    }

    /**
     * Persist categories and their relationships in the local database.
     *
     * @param array $categories Array of categories fetched from WordPress.
     * @throws \Exception If there's an error saving categories.
     */
    private function persistCategories(array $categories): void
    {
        try {
            $this->persistParentCategories($categories);
            $this->entityManager->flush();

            $this->persistChildCategories($categories);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw new \Exception('Error saving categories: ' . $e->getMessage());
        }
    }

    /**
     * Persist parent categories (categories with parent = 0).
     *
     * @param array $categories Array of categories fetched from WordPress.
     */
    private function persistParentCategories(array $categories): void
    {
        foreach ($categories as $categoryData) {
            if ($categoryData['parent'] === 0) {
                $category = $this->createCategory($categoryData);
                $this->entityManager->persist($category);
            }
        }
    }

    /**
     * Persist child categories and set their parent relationships.
     *
     * @param array $categories Array of categories fetched from WordPress.
     */
    private function persistChildCategories(array $categories): void
    {
        foreach ($categories as $categoryData) {
            if ($categoryData['parent'] != 0) {
                $parentCategory = $this->entityManager->getRepository(Category::class)->find($categoryData['parent']);
                if ($parentCategory) {
                    $subcategory = $this->createCategory($categoryData);
                    $subcategory->setParent($parentCategory);
                    $this->entityManager->persist($subcategory);
                }
            }
        }
    }

    /**
     * Create a Category entity from category data.
     *
     * @param array $categoryData Data of the category fetched from WordPress.
     * @return Category The created Category entity.
     */
    private function createCategory(array $categoryData): Category
    {
        $category = new Category();
        $category->setId($categoryData['id']);
        $category->setCount($categoryData['count']);
        $category->setDescription($categoryData['description']);
        $category->setName($categoryData['name']);
        $category->setSlug($categoryData['slug']);
        $category->setSlugSql($categoryData['slug']);
        
        return $category;
    }
}
