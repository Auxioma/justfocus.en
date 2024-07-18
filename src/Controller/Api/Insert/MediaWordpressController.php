<?php

namespace App\Controller\Api\Insert;

use App\Entity\Media;
use App\Repository\ArticlesRepository;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

class MediaWordpressController extends AbstractController
{
    #[Route('/api/media/', name: 'api_media_wordpress')]
    public function index(ArticlesRepository $articlesRepository, EntityManagerInterface $entityManager)
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

                    // Parcourir chaque média et les insérer en base de données
                    foreach ($mediaItems as $mediaItem) {
                        $media = new Media();
                        $media->setId($mediaItem['id']);
                        $media->setDate(new \DateTime($mediaItem['date'])); // Assurez-vous que 'date' correspond à un format valide
                        $media->setModified(new \DateTime($mediaItem['modified'])); // Assurez-vous que 'modified' correspond à un format valide
                        $media->setSlug($mediaItem['slug']);
                        $media->setGuid($mediaItem['guid']['rendered']); // Utilisez la clé 'rendered' pour obtenir la chaîne de caractères
                        
                        $maxTitleLength = 255; // Remplacez 255 par la longueur maximale autorisée de votre colonne 'title'
                        
                        $title = $mediaItem['title']['rendered'];
                        
                        if (strlen($title) > $maxTitleLength) {
                            $title = substr($title, 0, $maxTitleLength);
                        }
                        $media->setTitle($title);
                        
                        // Associer le média à l'article actuel
                        $media->setPost($article);

                        // Persister l'entité Media
                        $entityManager->persist($media);
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

        // Exécuter les opérations en base de données (commit)
        $entityManager->flush();

        // Retourner les données sous forme de réponse JSON
        return new JsonResponse($mediaData);
    }
}
