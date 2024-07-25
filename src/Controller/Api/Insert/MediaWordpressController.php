<?php

namespace App\Controller\Api\Insert;

use App\Entity\Media;
use App\Repository\ArticlesRepository;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class MediaWordpressController extends AbstractController
{
    #[Route('/api/media/', name: 'api_media_wordpress')]
    public function index(ArticlesRepository $articlesRepository, MediaRepository $mediaRepository)
    {
        // Retrieve all articles from the repository
        $articles = $articlesRepository->findAll();

        // WordPress API URL
        $wordpressApiUrl = 'https://justfocus.fr/wp-json/wp/v2/media';

        // Instantiate the Guzzle HTTP client
        $client = new Client();

        // Array to store media for each article
        $mediaData = [];

        // Iterate over each article
        foreach ($articles as $article) {
            $articleId = $article->getId();

            try {
                // Make the GET request to the WordPress API to fetch media for this article
                $response = $client->request('GET', $wordpressApiUrl, [
                    'query' => [
                        'parent' => $articleId,
                    ],
                ]);

                // Check the response status code
                if ($response->getStatusCode() === 200) {
                    // Decode the JSON response
                    $mediaItems = json_decode($response->getBody()->getContents(), true);

                    // Ensure there's at least one media item
                    if (count($mediaItems) > 0) {
                        $mediaItem = $mediaItems[0]; // Take only the first media item

                        // Check if the media already exists
                        $existingMedia = $mediaRepository->find($mediaItem['id']);
                        if ($existingMedia) {
                            // Skip existing media
                            continue;
                        }

                        // Download the image
                        $mediaUrl = $mediaItem['guid']['rendered'];
                        if ($mediaUrl) {
                            $imageContent = file_get_contents($mediaUrl);

                            // Generate the file path
                            $mediaId = $mediaItem['id'];
                            $mediaIdArray = str_split($mediaId);

                            $env = $_ENV['APP_ENV'] ?? 'prod';
                            $mediaPath = ($env === 'prod')
                                ? '/var/www/justfocus/public/images/' . implode('/', $mediaIdArray) . '/'
                                : 'C:\laragon\www\justfocus.en\public/images/' . implode('/', $mediaIdArray) . '/';

                            // Create the directory
                            if (!file_exists($mediaPath)) {
                                mkdir($mediaPath, 0777, true);
                            }

                            // Final file path for the WebP image
                            $mediaFileNameWebP = $mediaPath . $mediaId . '.webp';

                            // Convert the image to WebP
                            $image = imagecreatefromstring($imageContent);
                            if ($image !== false) {
                                // Check if the image is a palette image and convert to true color if necessary
                                if (!imageistruecolor($image)) {
                                    $trueColorImage = imagecreatetruecolor(imagesx($image), imagesy($image));
                                    imagecopy($trueColorImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                                    imagedestroy($image);
                                    $image = $trueColorImage;
                                }

                                imagewebp($image, $mediaFileNameWebP);
                                imagedestroy($image);
                                $mediaFileName = '/images/' . implode('/', $mediaIdArray) . '/' . $mediaId . '.webp';
                            } else {
                                $mediaFileName = '/images/default.webp';
                            }
                        } else {
                            $mediaFileName = '/images/default.webp';
                        }

                        $media = new Media();
                        $media->setId($mediaItem['id']);
                        $media->setDate(new \DateTime($mediaItem['date']));
                        $media->setModified(new \DateTime($mediaItem['modified']));
                        $media->setSlug($mediaItem['slug']);
                        $media->setGuid($mediaFileName);

                        $maxTitleLength = 255;
                        $title = $mediaItem['title']['rendered'];
                        if (strlen($title) > $maxTitleLength) {
                            $title = substr($title, 0, $maxTitleLength);
                        }
                        $media->setTitle($title);

                        // Associate the media with the current article
                        $media->setPost($article);

                        // Use the repository's save method to store the entity
                        $mediaRepository->save($media);
                        $mediaData[] = $media;
                    }
                } else {
                    // Handle request errors if necessary
                    $mediaData[$articleId] = ['error' => 'Error retrieving media.'];
                }
            } catch (\Exception $e) {
                // Handle exceptions if the request fails
                $mediaData[$articleId] = ['error' => 'Error retrieving media: ' . $e->getMessage()];
            }
        }

        // Return the data as a JSON response
        return new JsonResponse($mediaData);
    }
}
