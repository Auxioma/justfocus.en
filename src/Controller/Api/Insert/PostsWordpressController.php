<?php

namespace App\Controller\Api\Insert;

use DateTime;
use App\Entity\User;
use App\Entity\Media;
use App\Entity\Articles;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PostsWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 100;
    private $client;
    private $entityManager;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/insert/post', name: 'api_posts_wordpress')]
    public function insert()
    {  
        // Start date
        $startDate = new DateTime('2024-01-01');
        // End date (today)
        $endDate = new DateTime();

        // Fetch posts from WordPress API
        $response = $this->fetchPosts($startDate, $endDate);
        
        // Loop through each post in the response
        foreach ($response as $post) {
            // Check if the post already exists in the database
            $postExist = $this->entityManager->getRepository(Articles::class)->findOneBy(['id' => $post['id']]);

            if (!$postExist) {
                // If the post does not exist, create a new Articles entity
                $insertPost = new Articles();
                $insertPost->setId($post['id']);
                $insertPost->setTitle($post['title']['rendered']);
                $insertPost->setSlug($post['slug']);
                $insertPost->setContent($post['content']['rendered']);
                $insertPost->setDate(new DateTime($post['date']));
                $insertPost->setModified(new DateTime($post['modified']));

                // Create the relationship between articles and categories
                foreach ($post['categories'] as $category) {
                    $categoryExist = $this->entityManager->getRepository(Category::class)->findOneBy(['id' => $category]);
                    if ($categoryExist) {
                        $insertPost->addCategory($categoryExist);
                    }
                }

                // Create the relationship between articles and users
                $userExist = $this->entityManager->getRepository(User::class)->findOneBy(['id' => $post['author']]);
                if ($userExist) {
                    $insertPost->setUser($userExist);
                }

                // je vais utilise l'api pour récupérer les médias
                $mediaApi = $this->client->request('GET', sprintf('https://justfocus.fr/wp-json/wp/v2/media/%d', $post['featured_media']));
                
                // si le status code est différent de 200 on passe à l'itération suivante
                if ($mediaApi->getStatusCode() !== 200) {
                    continue;
                }
                
                $media = $mediaApi->toArray();

                // Create the relationship between articles and media
                $mediaExist = $this->entityManager->getRepository(Media::class)->findOneBy(['id' => $media['id']]);
                if (!$mediaExist) {
                    $insertMedia = new Media();
                    $insertMedia->setId($media['id']);
                    $insertMedia->setTitle($media['title']['rendered']);
                    $insertMedia->setGuid($media['guid']['rendered']);
                    $insertMedia->setSlug($media['slug']);
                    $insertMedia->setDate(new DateTime($media['date']));
                    $insertMedia->setModified(new DateTime($media['modified']));
                    $insertMedia->setPost($insertPost);

                    $this->entityManager->persist($insertMedia);
                    $this->entityManager->flush();

                }

                // Persist the new post to the database
                $this->entityManager->persist($insertPost);
                
            } elseif ($postExist->getModified() < new DateTime($post['modified'])) {
                // If the post exists but has been modified, update it
                $postExist->setTitle($post['title']['rendered']);
                $postExist->setSlug($post['slug']);
                $postExist->setContent($post['content']['rendered']);
                $postExist->setDate(new DateTime($post['date']));
                $postExist->setModified(new DateTime($post['modified']));

                // Persist the updated post to the database
                $this->entityManager->persist($postExist);
            }
        }

        // Flush the changes to the database
        $this->entityManager->flush();

        // Return a JSON response
        return $this->json(['message' => 'success', 'data' => $response]);
    }

    private function fetchPosts(DateTime $startDate, DateTime $endDate)
    {
        // Format the start and end dates
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate->format('Y-m-d');
        
        // Construct the URL for the WordPress API request
        $url = sprintf('%s?after=%sT00:00:00&before=%sT23:59:59&per_page=%d', self::WORDPRESS_API_URL, $startDateStr, $endDateStr, self::PER_PAGE);
        
        // Send the request to the WordPress API
        $response = $this->client->request('GET', $url);

        // Check if the response status code is not 200 (OK)
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error fetching posts from WordPress.');
        }

        // Return the response data as an array
        return $response->toArray();
    }
}
