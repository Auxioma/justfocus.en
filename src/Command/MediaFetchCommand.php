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

        // Récupérer tous les articles sans médias
        $articles = $this->articlesRepository->findArticlesWithoutMedia();

        // WordPress API URL
        $wordpressApiUrl = 'https://justfocus.fr/wp-json/wp/v2/media';

        // Initialiser le client HTTP Guzzle
        $client = new Client();

        $successCount = 0;
        $errorCount = 0;
        $mediaData = [];

        foreach ($articles as $article) {
            $articleId = $article->getId();

            try {
                // Faire la requête GET pour récupérer les médias
                $response = $client->request('GET', $wordpressApiUrl, [
                    'query' => [
                        'parent' => $articleId,
                    ],
                ]);

                if (200 === $response->getStatusCode()) {
                    $mediaItems = json_decode($response->getBody()->getContents(), true);

                    // Ensure the response is an array
                    if (is_array($mediaItems) && count($mediaItems) > 0) {
                        $mediaItem = $mediaItems[0];  // Assuming we're dealing with the first media item

                        // Check if $mediaItem is an array and contains the necessary keys
                        if (is_array($mediaItem) && isset($mediaItem['id'], $mediaItem['guid']['rendered'])) {
                            $existingMedia = $this->mediaRepository->find($mediaItem['id']);
                            if ($existingMedia) {
                                $output->writeln("Le média avec ID {$mediaItem['id']} existe déjà. Passer...");
                                continue;
                            }

                            $mediaUrl = $mediaItem['guid']['rendered'];
                            if ($mediaUrl) {
                                $imageContent = @file_get_contents($mediaUrl);

                                // Ensure the image content is valid
                                if (false !== $imageContent) {
                                    $imageInfo = getimagesizefromstring($imageContent);
                                    if (false === $imageInfo) {
                                        $io->error("Le fichier récupéré pour l'ID {$mediaItem['id']} n'est pas une image valide.");
                                        $mediaData[$articleId] = 'Fichier non valide';
                                        ++$errorCount;
                                        continue;
                                    }

                                    // Proceed with saving the media
                                    $mediaId = (string) $mediaItem['id'];  // Cast ID to string
                                    $mediaIdArray = str_split($mediaId);

                                    $env = $_ENV['APP_ENV'] ?? 'prod';
                                    $mediaPath = ('prod' === $env)
                                        ? '/home/favevace/www/public/images/'.implode('/', $mediaIdArray).'/'
                                        : 'C:\laragon\www\justfocus.en\public/images/'.implode('/', $mediaIdArray).'/';

                                    if (!file_exists($mediaPath)) {
                                        mkdir($mediaPath, 0777, true);
                                    }

                                    $mediaFileNameWebP = $mediaPath.$mediaId.'.webp';

                                    // Create and convert image to WebP
                                    $image = imagecreatefromstring($imageContent);
                                    if (false !== $image) {
                                        if (!imageistruecolor($image)) {
                                            $trueColorImage = imagecreatetruecolor(imagesx($image), imagesy($image));
                                            imagecopy($trueColorImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                                            imagedestroy($image);
                                            $image = $trueColorImage;
                                        }

                                        imagewebp($image, $mediaFileNameWebP);
                                        imagedestroy($image);
                                        $mediaFileName = '/images/'.implode('/', $mediaIdArray).'/'.$mediaId.'.webp';
                                    } else {
                                        $mediaFileName = '/images/default.webp';
                                    }

                                    // Enregistrer le média
                                    $media = new Media();
                                    $media->setId((int) $mediaItem['id']);  // Cast ID to integer
                                    $media->setDate(new \DateTime($mediaItem['date']));
                                    $media->setModified(new \DateTime($mediaItem['modified']));
                                    $media->setSlug($mediaItem['slug'] ?? '');  // Handle missing slug
                                    $media->setGuid($mediaFileName);

                                    $title = $mediaItem['title']['rendered'] ?? '';  // Handle missing title
                                    $maxTitleLength = 255;
                                    if (strlen($title) > $maxTitleLength) {
                                        $title = substr($title, 0, $maxTitleLength);
                                    }
                                    $media->setTitle($title);

                                    // Associer le média à l'article
                                    $media->setPost($article);

                                    // Sauvegarder l'entité dans la base de données
                                    $this->mediaRepository->save($media);

                                    $io->success("Média avec ID {$mediaItem['id']} téléchargé et sauvegardé avec succès.");
                                    $mediaData[$articleId] = 'Téléchargé et sauvegardé avec succès';
                                    ++$successCount;
                                }
                            }
                        } else {
                            $io->warning("Aucun média trouvé pour l'article ID {$articleId}.");
                        }
                    }
                }
            } catch (\Exception $e) {
                $io->error("Erreur lors de la récupération du média pour l'article ID {$articleId} : ".$e->getMessage());
                $mediaData[$articleId] = 'Erreur : '.$e->getMessage();
                ++$errorCount;
            }
        }

        $io->success("Médias récupérés : {$successCount} succès, {$errorCount} erreurs.");
        $io->table(['Article ID', 'Statut'], array_map(fn ($id, $status) => [$id, $status], array_keys($mediaData), $mediaData));

        return Command::SUCCESS;
    }
}
