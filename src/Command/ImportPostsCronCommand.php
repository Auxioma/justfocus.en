<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use App\Entity\Articles;

#[AsCommand(
    name: 'app:import-posts',
    description: 'Imports posts from a WordPress API and stores them in the database'
)]
final class ImportPostsCronCommand extends Command
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts';
    private const PER_PAGE = 100;

    private HttpClientInterface $client;
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(
        HttpClientInterface $client,
        ArticlesRepository $articlesRepository,
        CategoryRepository $categoryRepository,
        UserRepository $userRepository,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->client = $client;
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->userRepository = $userRepository;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting the import of posts from WordPress API...');

        $categories = $this->categoryRepository->findAll();

        if (!$categories) {
            $output->writeln('No categories found.');
            return Command::FAILURE;
        }

        $insertedCount = 0;
        $maxInserts = 50;

        foreach ($categories as $category) {
            $categoryId = $category->getId();
            $page = 1;

            do {
                $url = self::WORDPRESS_API_URL . '?categories=' . $categoryId . '&per_page=' . self::PER_PAGE . '&page=' . $page . '&status=publish';
                $response = $this->client->request('GET', $url);

                if ($response->getStatusCode() !== 200) {
                    $this->logger->error('Failed to fetch posts from WordPress for category ' . $categoryId);
                    break;
                }

                $categoryPosts = $response->toArray();
                if (empty($categoryPosts)) {
                    break;
                }

                $this->entityManager->beginTransaction();
                try {
                    foreach ($categoryPosts as $postData) {
                        if ($insertedCount >= $maxInserts) {
                            $output->writeln('Maximum number of articles inserted. Ending import.');
                            $this->entityManager->commit();
                            return Command::SUCCESS;
                        }

                        $existingArticle = $this->articlesRepository->find($postData['id']);

                        if ($existingArticle) {
                            $output->writeln('Article with ID ' . $postData['id'] . ' already exists. Skipping...');
                            continue;
                        }

                        $article = new Articles();
                        $this->populateArticle($article, $postData);
                        $this->articlesRepository->save($article);

                        $output->writeln('Article with ID ' . $postData['id'] . ' inserted.');
                        $insertedCount++;
                    }
                    $this->entityManager->commit();
                } catch (\Exception $e) {
                    $this->entityManager->rollback();
                    $this->logger->error('Failed to process posts: ' . $e->getMessage());
                    $output->writeln('Error: ' . $e->getMessage());
                    return Command::FAILURE;
                }

                $page++;
            } while (count($categoryPosts) === self::PER_PAGE);
        }

        $output->writeln('Articles successfully inserted.');
        return Command::SUCCESS;
    }

    private function populateArticle(Articles $article, array $postData): void
    {
        $article->setId($postData['id']);
        $article->setTitle(html_entity_decode($postData['title']['rendered'] ?? 'No title'));
        $article->setSlug(html_entity_decode($postData['slug'] ?? ''));
        $article->setDate(new \DateTime($postData['date'] ?? 'now'));
        $article->setModified(new \DateTime($postData['modified'] ?? 'now'));
        $article->setContent(html_entity_decode($postData['content']['rendered'] ?? ''));

        foreach ($postData['categories'] ?? [] as $catId) {
            $category = $this->categoryRepository->find($catId);
            if ($category) {
                $article->addCategory($category);
            }
        }

        $userId = $postData['author'] ?? null;
        if ($userId) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $article->setUser($user);
            }
        }
    }
}
