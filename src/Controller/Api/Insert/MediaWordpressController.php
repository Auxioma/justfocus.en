<?php

namespace App\Controller\Api\Insert;

use App\Entity\Media;
use App\Entity\Articles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MediaWordpressController extends AbstractController
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 100; // Number of posts per page (adjust as needed)
    private $client;
    private $entityManager;

    public function __construct(HttpClientInterface $client, EntityManagerInterface $entityManager)
    {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/media/', name: 'api_media_wordpress')]
    public function index()
    {
        // Fetch posts from the WordPress API
        $response = $this->fetchPosts();
        
        // Initialize an array to hold media and titles
        $mediaAndTitles = [];

        // Loop through each post in the response
        foreach ($response as $post) {
            // Extract the required data
            $title = $post['title']['rendered'];
            $mediaUrl = isset($post['featured_media']) ? $this->fetchMediaUrl($post['featured_media']) : null;
            $slug = $post['slug'];
            $date = new \DateTime($post['date']);
            $modified = new \DateTime($post['modified']);
            $Id = $post['id'];
            $Post = $this->fetchMediaUrl($post['post']);

            // je met en base de donnée les données si l'id n'existent pas
            $media = $this->entityManager->getRepository(Media::class)->findOneBy(['id' => $Id]);
            if (!$media) {
                $media = new Media();
                $media->setId($Id);
                $media->setTitle($title);
                $media->setGuid($mediaUrl);
                $media->setSlug($slug);
                $media->setDate($date);
                $media->setModified($modified);

                // je vais chercher l'objet article pour le lier à l'objet media
                $article = $this->entityManager->getRepository(Articles::class)->findOneBy(['id' => $Post]);
                if ($article) {
                    $media->setPost($article);
                }

                $this->entityManager->persist($media);
                $this->entityManager->flush();
            }
        }

        // Return the media and titles as a JSON response
        return $this->json(['message' => 'success', 'data' => $mediaAndTitles]);
    }

    private function fetchPosts()
    {
        $url = sprintf('%s?per_page=%d', self::WORDPRESS_API_URL, self::PER_PAGE);

        $response = $this->client->request('GET', $url);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Error fetching posts from WordPress.');
        }

        return $response->toArray();
    }

    private function fetchMediaUrl($mediaId)
    {
        $mediaUrl = null;
        $mediaResponse = $this->client->request('GET', sprintf('https://justfocus.fr/wp-json/wp/v2/media/%d', $mediaId));

        if ($mediaResponse->getStatusCode() === 200) {
            $mediaData = $mediaResponse->toArray();
            if (isset($mediaData['source_url'])) {
                $mediaUrl = $mediaData['source_url'];
            }
        }

        return $mediaUrl;
    }
}
