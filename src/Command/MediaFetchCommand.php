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

        // Variables pour stocker le nombre de succès et d'échecs
        $successCount = 0;
        $errorCount = 0;
        $mediaData = [];  // Stocker des infos sur chaque article

        foreach ($articles as $article) {
            $articleId = $article->getId();

            try {
                // Faire la requête GET pour récupérer les médias
                $response = $client->request('GET', $wordpressApiUrl, [
                    'query' => [
                        'parent' => $articleId,
                    ],
                ]);

                // Vérifier le statut de la réponse
                if (200 === $response->getStatusCode()) {
                    $mediaItems = json_decode($response->getBody()->getContents(), true);

                    if (count($mediaItems) > 0) {
                        $mediaItem = $mediaItems[0];

                        // Vérifier si le média existe déjà
                        $existingMedia = $this->mediaRepository->find($mediaItem['id']);
                        if ($existingMedia) {
                            $output->writeln("Le média avec ID {$mediaItem['id']} existe déjà. Passer...");
                            continue;
                        }

                        // Récupérer l'URL du média
                        $mediaUrl = $mediaItem['guid']['rendered'];
                        if ($mediaUrl) {
                            // Télécharger le contenu de l'image
                            $imageContent = file_get_contents($mediaUrl);

                            // Vérifier si le contenu est bien une image
                            $imageInfo = getimagesizefromstring($imageContent);
                            if (false === $imageInfo) {
                                $io->error("Le fichier récupéré pour l'ID {$mediaItem['id']} n'est pas une image valide.");
                                $mediaData[$articleId] = 'Fichier non valide';
                                ++$errorCount;
                                continue;
                            }

                            // Générer le chemin du fichier
                            $mediaId = $mediaItem['id'];
                            $mediaIdArray = str_split($mediaId);

                            $env = $_ENV['APP_ENV'] ?? 'prod';
                            $mediaPath = ('prod' === $env)
                                ? '/home/favevace/www/public/images/'.implode('/', $mediaIdArray).'/'
                                : 'C:\laragon\www\justfocus.en\public/images/'.implode('/', $mediaIdArray).'/';

                            if (!file_exists($mediaPath)) {
                                mkdir($mediaPath, 0777, true);
                            }

                            // Créer un nom de fichier pour le format WebP
                            $mediaFileNameWebP = $mediaPath.$mediaId.'.webp';

                            // Créer l'image à partir du contenu téléchargé
                            $image = imagecreatefromstring($imageContent);
                            if (false !== $image) {
                                // Convertir en WebP
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
                                $mediaFileName = '/images/'.implode('/', $mediaIdArray).'/'.$mediaId.'.png';
                            }
                        } else {
                            $mediaFileName = '/images/default.webp';
                        }

                        // Enregistrer le média
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

                        // Associer le média à l'article
                        $media->setPost($article);

                        // Sauvegarder l'entité dans la base de données
                        $this->mediaRepository->save($media);

                        $io->success("Média avec ID {$mediaItem['id']} téléchargé et sauvegardé avec succès.");
                        $mediaData[$articleId] = 'Téléchargé et sauvegardé avec succès';
                        ++$successCount;
                    } else {
                        $io->warning("Aucun média trouvé pour l'article ID {$articleId}.");
                    }
                }
            } catch (\Exception $e) {
                // Gérer les exceptions
                $io->error("Erreur lors de la récupération du média pour l'article ID {$articleId} : ".$e->getMessage());
                $mediaData[$articleId] = 'Erreur : '.$e->getMessage();
                ++$errorCount;
            }
        }

        // Résumé des opérations
        $io->success("Médias récupérés : {$successCount} succès, {$errorCount} erreurs.");
        $io->table(['Article ID', 'Statut'], array_map(fn ($id, $status) => [$id, $status], array_keys($mediaData), $mediaData));

        return Command::SUCCESS;
    }
}
