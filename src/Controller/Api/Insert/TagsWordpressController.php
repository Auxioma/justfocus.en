<?php

namespace App\Controller\Api\Insert;

use App\Entity\Tags;
use App\Repository\ArticlesRepository;
use App\Repository\TagsRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TagsWordpressController extends AbstractController
{
    private ArticlesRepository $articlesRepository;
    private TagsRepository $tagsRepository;
    private HttpClientInterface $httpClient;
    private SluggerInterface $slugger;

    public function __construct(
        ArticlesRepository $articlesRepository,
        TagsRepository $tagsRepository,
        HttpClientInterface $httpClient,
        SluggerInterface $slugger
    ) {
        $this->articlesRepository = $articlesRepository;
        $this->tagsRepository = $tagsRepository;
        $this->httpClient = $httpClient;
        $this->slugger = $slugger;
    }

    #[Route('/api/insert/tags/wordpress', name: 'api_insert_tags_wordpress')]
    public function index()
    {
        $articles = $this->articlesRepository->findAll();
        $tags = [];

        foreach ($articles as $article) {
            $uri = 'https://justfocus.fr/wp-json/wp/v2/posts/' . $article->getId();
            $response = $this->httpClient->request('GET', $uri);

            if ($response->getStatusCode() !== 200) {
                continue; // Ignorer cet article s'il y a une erreur de requête
            }

            $tagsArticle = json_decode($response->getContent(), true);

            if (isset($tagsArticle['tags']) && is_array($tagsArticle['tags'])) {
                foreach ($tagsArticle['tags'] as $tagId) {
                    $tagUri = 'https://justfocus.fr/wp-json/wp/v2/tags/' . $tagId;
                    $tagResponse = $this->httpClient->request('GET', $tagUri);

                    if ($tagResponse->getStatusCode() !== 200) {
                        continue;
                    }

                    $tagData = json_decode($tagResponse->getContent(), true);

                    if (isset($tagData['id'], $tagData['name'])) {
                        $tags[$tagData['id']] = $tagData['name'];
                    }
                }
            }
        }

        foreach ($tags as $id => $name) {
            $tag = $this->tagsRepository->findOneBy(['slug' => $id]);

            if (!$tag) {
                $newTag = new Tags();
                $newTag->setId($id);
                $newTag->setName($name);
                $newTag->setSlug($this->slugger->slug($name));

                // Ajouter les articles au nouveau tag
                foreach ($articles as $article) {
                    $newTag->addArticle($article);
                }

                $this->tagsRepository->save($newTag);
            }
        }

        return $this->json([
            'message' => 'Les tags ont bien été insérés'
        ]);
    }
}