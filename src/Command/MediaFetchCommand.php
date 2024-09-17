<?php

namespace App\Command;

use App\Entity\Media;
use App\Repository\ArticlesRepository;
use App\Repository\MediaRepository;
use GuzzleHttp\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-media',
    description: 'Fetch media from WordPress and save to local database',
)]
class MediaFetchCommand extends Command
{
    private ArticlesRepository $articlesRepository;
    private MediaRepository $mediaRepository;

    public function __construct(ArticlesRepository $articlesRepository, MediaRepository $mediaRepository)
    {
        parent::__construct();
        $this->articlesRepository = $articlesRepository;
        $this->mediaRepository = $mediaRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Fetch media from WordPress and save to local database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Retrieve all articles from the repository
        $articles = $this->articlesRepository->findAll();

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
                        $existingMedia = $this->mediaRepository->find($mediaItem['id']);
                        if ($existingMedia) {
                            // Skip existing media and output a message
                            $output->writeln("Media with ID {$mediaItem['id']} already exists. Skipping...");
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
                                ? '/home/favevace/www/public/images/' . implode('/', $mediaIdArray) . '/'
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
                                $mediaFileName = '/images/' . implode('/', $mediaIdArray) . '/' . $mediaId . '.png';
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
                        $this->mediaRepository->save($media);

                        $output->writeln("Media with ID {$mediaItem['id']} downloaded and saved successfully.");

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

        $io->success('Media fetched successfully.');
        return Command::SUCCESS;
    }
}
