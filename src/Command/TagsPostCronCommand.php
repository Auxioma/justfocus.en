<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Tags;
use App\Entity\Articles;

#[AsCommand(
    name: 'app:import-article-tags',
    description: 'Imports tags for articles from WordPress API and stores them in the database'
)]
final class TagsPostCronCommand extends Command
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/tags';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $httpClient = HttpClient::create();
    
        // Récupérer les articles sans tags
        $articles = $this->entityManager->getRepository(Articles::class)->findArticlesWithoutTags();
    
        // Initialiser un compteur pour limiter à 100 articles et un autre pour les tags insérés
        $counter = 0;
        $tagsInserted = 0;
    
        $iterator = new \ArrayIterator($articles);
    
        do {
            if (!$iterator->valid()) {
                break;
            }
    
            $article = $iterator->current();
            $articleId = $article->getId();
    
            // Récupérer les tags de l'article via l'API WordPress
            $response = $httpClient->request('GET', self::WORDPRESS_API_URL, [
                'query' => [
                    'post' => $articleId
                ]
            ]);
    
            if ($response->getStatusCode() !== 200) {
                $output->writeln("Error fetching tags for article ID {$articleId}, skipping.");
                $iterator->next();
                continue;
            }
    
            $tags = $response->toArray();
    
            if (empty($tags)) {
                $output->writeln("No tags found for article ID {$articleId}");
                $iterator->next();
                continue;
            }
    
            foreach ($tags as $tagData) {
                $existingTag = $this->entityManager->getRepository(Tags::class)->findOneBy([
                    'id' => $tagData['id']
                ]);
    
                if (!$existingTag) {
                    $tag = new Tags();
                    $tag->setName($tagData['name']);
                    $tag->setSlug($tagData['slug']);
                    $tag->setId($tagData['id']);
                    $this->entityManager->persist($tag);
    
                    // Incrémenter le compteur de tags insérés
                    $tagsInserted++;
                } else {
                    $existingTag->setName($tagData['name']);
                    $tag = $existingTag;
                    $this->entityManager->persist($tag);
                }
    
                if (!$article->getTags()->contains($tag)) {
                    $article->addTag($tag);
                    $output->writeln("Tag '{$tag->getName()}' linked to article ID {$articleId}");
                }
            }
    
            $this->entityManager->flush();
            // $this->entityManager->clear(Tags::class);
    
            $output->writeln("Tags inserted or updated for article ID {$articleId}");
    
            $counter++;
            $iterator->next();
    
        } while ($counter < 100);
    
        // Afficher le nombre total de tags insérés
        $output->writeln("Total tags processed: {$tagsInserted}");
    
        return Command::SUCCESS;
    }
    
}
