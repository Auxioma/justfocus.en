<?php

namespace App\Controller\Api\Insert;

use App\Entity\Media;
use App\Entity\Articles;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Doctrine\Persistence\ManagerRegistry;

class MediaWordpressController extends AbstractController
{
    private $client;
    private $doctrine;

    public function __construct(HttpClientInterface $client, ManagerRegistry $doctrine)
    {
        $this->client = $client;
        $this->doctrine = $doctrine;
    }

    #[Route('/api/wordpress/media', name: 'app_api_media', methods: ['GET'])]
    public function index(): Response
    {
        $URI = 'https://justfocus.fr/wp-json/wp/v2/media';

        $allMedias = [];
        $page = 1;
        $perPage = 100;
        $maxRetries = 3; // Nombre maximal de tentatives en cas d'échec
        $retryDelay = 2; // Délai (en secondes) entre les tentatives
        $apiDelay = 1; // Délai (en secondes) entre les appels API pour éviter la surcharge

        try {
            do {
                $response = $this->makeApiRequest($URI, $perPage, $page, $maxRetries, $retryDelay);

                $media = $response->toArray();
                $allMedias = array_merge($allMedias, $media);
                $page++;
                
                // Pause entre les appels API pour éviter la surcharge
                sleep($apiDelay);
            } while (count($media) === $perPage);

            $entityManager = $this->doctrine->getManager();

            foreach ($allMedias as $media) {
                $insertMedia = new Media();
                $insertMedia->setId($media['id']);
                $insertMedia->setDate(new \DateTime($media['date']));
                $insertMedia->setModified(new \DateTime($media['modified']));
                $insertMedia->setSlug($media['slug']);
                $insertMedia->setGuid($media['guid']['rendered']);
                $insertMedia->setTitle($media['title']['rendered']);

                // Relation avec l'article
                $article = $this->doctrine->getRepository(Articles::class)->find($media['post']);
                $insertMedia->setPost($article);

                $entityManager->persist($insertMedia);
            }

            $entityManager->flush();

            return new JsonResponse(['status' => 'success', 'data' => $allMedias]);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function makeApiRequest($URI, $perPage, $page, $maxRetries, $retryDelay)
    {
        $retries = 0;
        while (true) {
            try {
                return $this->client->request('GET', $URI, [
                    'query' => [
                        'per_page' => $perPage,
                        'page' => $page,
                    ],
                ]);
            } catch (TransportExceptionInterface | ClientExceptionInterface | RedirectionExceptionInterface | ServerExceptionInterface $e) {
                if ($retries >= $maxRetries) {
                    throw $e;
                }
                $retries++;
                sleep($retryDelay);
            }
        }
    }
}
