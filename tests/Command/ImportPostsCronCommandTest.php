<?php

namespace App\Tests\Command;

use App\Command\ImportPostsCronCommand;
use App\Entity\Articles;
use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Psr\Log\LoggerInterface;

class ImportPostsCronCommandTest extends TestCase
{
    private $client;
    private $articlesRepository;
    private $categoryRepository;
    private $userRepository;
    private $logger;
    private $entityManager;

    public function setUp(): void
    {
        $this->client = $this->createMock(HttpClientInterface::class);
        $this->articlesRepository = $this->createMock(ArticlesRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    public function testExecuteSuccessfulImport()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([[
                'id' => 1,
                'title' => ['rendered' => 'Test Title'],
                'slug' => 'test-title',
                'date' => '2023-09-01T00:00:00',
                'modified' => '2023-09-01T12:00:00',
                'content' => ['rendered' => 'Test content'],
                'categories' => [],
                'author' => 1
            ]]);

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->articlesRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('commit');
        $this->articlesRepository->expects($this->once())->method('save');

        $command = new ImportPostsCronCommand(
            $this->client,
            $this->articlesRepository,
            $this->categoryRepository,
            $this->userRepository,
            $this->logger,
            $this->entityManager
        );
        
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([]);

        $this->assertEquals(0, $result);
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Starting the import of posts from WordPress API...', $commandTester->getDisplay());
        $this->assertStringContainsString('Article with ID 1 inserted.', $commandTester->getDisplay());
    }

    public function testExecuteWithApiError()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(500);

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Une erreur s\'est produite lors de la récupération des articles.');

        $command = new ImportPostsCronCommand(
            $this->client,
            $this->articlesRepository,
            $this->categoryRepository,
            $this->userRepository,
            $this->logger,
            $this->entityManager
        );
        
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([]);

        $this->assertEquals(1, $result); // Command::FAILURE is expected
    }

    public function testExecuteWithExistingArticle()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([[
                'id' => 1,
                'title' => ['rendered' => 'Test Title'],
                'slug' => 'test-title',
                'date' => '2023-09-01T00:00:00',
                'modified' => '2023-09-01T12:00:00',
                'content' => ['rendered' => 'Test content'],
                'categories' => [],
                'author' => 1
            ]]);

        $article = $this->createMock(Articles::class);

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->articlesRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($article);

        $this->entityManager->expects($this->never())->method('beginTransaction');
        $this->entityManager->expects($this->never())->method('commit');
        $this->articlesRepository->expects($this->never())->method('save');

        $command = new ImportPostsCronCommand(
            $this->client,
            $this->articlesRepository,
            $this->categoryRepository,
            $this->userRepository,
            $this->logger,
            $this->entityManager
        );

        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([]);

        $this->assertEquals(0, $result);
        $commandTester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Article with ID 1 already exists. Skipping.', $commandTester->getDisplay());
    }

    public function testTransactionRollbackOnException()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $response->expects($this->once())
            ->method('toArray')
            ->willReturn([[
                'id' => 1,
                'title' => ['rendered' => 'Test Title'],
                'slug' => 'test-title',
                'date' => '2023-09-01T00:00:00',
                'modified' => '2023-09-01T12:00:00',
                'content' => ['rendered' => 'Test content'],
                'categories' => [],
                'author' => 1
            ]]);

        $this->client->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->articlesRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');

        $this->articlesRepository->expects($this->once())
            ->method('save')
            ->will($this->throwException(new \Exception('Database error')));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to process posts: Database error');

        $command = new ImportPostsCronCommand(
            $this->client,
            $this->articlesRepository,
            $this->categoryRepository,
            $this->userRepository,
            $this->logger,
            $this->entityManager
        );
        
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([]);

        $this->assertEquals(1, $result); // Command::FAILURE is expected
        $this->assertStringContainsString('Error: Database error', $commandTester->getDisplay());
    }
}
