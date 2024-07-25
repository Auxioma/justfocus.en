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
        // Récupérer tous les articles depuis le repository
        $articles = $articlesRepository->findAll();

        // URL de l'API WordPress
        $wordpressApiUrl = 'https://justfocus.fr/wp-json/wp/v2/media';

        // Instanciation du client Guzzle HTTP
        $client = new Client();

        // Tableau pour stocker les médias de chaque article
        $mediaData = [];

        // Parcourir chaque article
        foreach ($articles as $article) {
            $articleId = $article->getId();

            try {
                // Faire la requête GET à l'API WordPress pour récupérer les médias de cet article
                $response = $client->request('GET', $wordpressApiUrl, [
                    'query' => [
                        'parent' => $articleId,
                    ],
                ]);

                // Vérifier le code de statut de la réponse
                if ($response->getStatusCode() === 200) {
                    // Décoder la réponse JSON
                    $mediaItems = json_decode($response->getBody()->getContents(), true);

                    // Assurer qu'il y ait au moins un média
                    if (count($mediaItems) > 0) {
                        $mediaItem = $mediaItems[0]; // Prendre seulement le premier média

                        // Vérifier si le média existe déjà
                        $existingMedia = $mediaRepository->find($mediaItem['id']);
                        if ($existingMedia) {
                            // Ignorer les médias existants
                            continue;
                        }

                        // Télécharger l'image
                        $mediaUrl = $mediaItem['guid']['rendered'];
                        if ($mediaUrl) {
                            $imageContent = file_get_contents($mediaUrl);

                            // Générer le chemin du fichier
                            $mediaId = $mediaItem['id'];
                            $mediaIdArray = str_split($mediaId);
                            $mediaPath = '/home/zuhpqwez/public_html/public/images/' . implode('/', $mediaIdArray) . '/';

                            // Générer un ID unique plus sécurisé
                            // $uniqueId = uniqid() . '-' . md5(uniqid(rand(), true));
                            $uniqueId = $mediaId;

                            // Créer le dossier
                            if (!file_exists($mediaPath)) {
                                mkdir($mediaPath, 0777, true);
                            }

                            // Chemin du fichier final en WEBP
                            $mediaFileNameWebP = $mediaPath . $uniqueId . '.webp';

                            // Convertir l'image en WEBP
                            $image = imagecreatefromstring($imageContent);
                            if ($image !== false) {
                                imagewebp($image, $mediaFileNameWebP);
                                imagedestroy($image);
                                $mediaFileName = '/images/' . implode('/', $mediaIdArray) . '/' . $uniqueId . '.webp';
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

                        // Associer le média à l'article actuel
                        $media->setPost($article);

                        // Utiliser la méthode save du repository pour enregistrer l'entité
                        $mediaRepository->save($media);
                        $mediaData[] = $media;
                    }
                } else {
                    // Gérer les erreurs de requête si nécessaire
                    $mediaData[$articleId] = ['error' => 'Erreur lors de la récupération des médias.'];
                }
            } catch (\Exception $e) {
                // Gérer les exceptions si la requête échoue
                $mediaData[$articleId] = ['error' => 'Erreur lors de la récupération des médias : ' . $e->getMessage()];
            }
        }

        // Retourner les données sous forme de réponse JSON
        return new JsonResponse($mediaData);
    }
}
