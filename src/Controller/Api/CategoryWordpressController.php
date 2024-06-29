<?php

namespace App\Controller\Api;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes categories from the WordPress API.
 * I chose to synchronize categories from the WordPress API to the local database.
 * I use only for initial synchronization, so I don't need to worry about performance.
 */
class CategoryWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/categories?per_page=100&lang=fr';

    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

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
     * Fetches categories from the WordPress API.
     *
     * @return array
     * @throws \Exception
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
     * Persists categories and their relationships.
     *
     * @param array $categories
     * @throws \Exception
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
     * Persists parent categories (those with parent = 0).
     *
     * @param array $categories
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
     * Persists child categories and sets their parent relationships.
     *
     * @param array $categories
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
     * Creates a Category entity from category data.
     *
     * @param array $categoryData
     * @return Category
     */
    private function createCategory(array $categoryData): Category
    {
        $category = new Category();
        $category->setId($categoryData['id']);
        $category->setCount($categoryData['count']);
        $category->setDescription($categoryData['description']);
        $category->setName($categoryData['name']);
        $category->setSlug($categoryData['slug']);
        
        return $category;
    }
}
