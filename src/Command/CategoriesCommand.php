<?php

namespace App\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsCommand(
    name: 'app:categories',
    description: 'Fetch categories from external API (with pagination) and update the database',
)]
final class CategoriesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private SluggerInterface $slugger;

    public function __construct(EntityManagerInterface $entityManager, SluggerInterface $slugger)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->slugger = $slugger;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $baseURL = 'https://justfocus.fr/wp-json/wp/v2/categories?lang=fr';
        $page = 1;
        $categories = [];

        try {
            do {
                // Fetch the current page of categories
                $url = $baseURL . '&page=' . $page;
                $response = file_get_contents($url);
                $pageData = json_decode($response, true);

                if (!is_array($pageData)) {
                    $output->writeln('<error>Invalid response format from API</error>');
                    return Command::FAILURE;
                }

                if (empty($pageData)) {
                    break; // Sortir si aucune donnée n'est retournée
                }

                // Merge current page categories into the total collection
                $categories = array_merge($categories, $pageData);

                $output->writeln("<info>Fetched page $page with " . count($pageData) . " categories</info>");

                // Récupération des headers de pagination
                $paginationHeaders = $this->getPaginationHeaders($http_response_header);
                $totalPages = $paginationHeaders['X-WP-TotalPages'] ?? 1;
                $page++; // Page suivante

            } while ($page <= $totalPages);
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to fetch categories from API: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        // Première boucle : insertion des catégories parents
        foreach ($categories as $catData) {
            $id = $catData['id'] ?? null;
            $parentId = $catData['parent'] ?? null;
            $count = $catData['count'] ?? null;
            $description = $catData['description'] ?? '';
            $name = $catData['name'] ?? '';
            $slugSQL = $catData['slug'] ?? '';

            if (!$id) {
                $output->writeln('<comment>Skipping category with missing ID...</comment>');
                continue;
            }

            // On s'occupe uniquement des catégories dont parentId est 0 (catégories parentes)
            if ($parentId === 0) {

                $authKey = $_ENV['DEEPL_API_KEY'] ?? null;
                $translator = new \DeepL\Translator($authKey);
    
                $translations = $translator->translateText(
                    [
                        $name,
                    ],
                    null,
                    'en-GB',
                );

                $TransformSlug = $this->slugger->slug($translations[0]->text);

                $existingCategory = $this->entityManager->getRepository(Category::class)->findOneBy(['id' => $id]);

                if ($existingCategory) {
                    // Mise à jour de la catégorie existante
                    $existingCategory->setName($translations[0]->text);
                    $existingCategory->setCount($count);
                    $existingCategory->setDescription($description);
                    $existingCategory->setSlug($TransformSlug);
                    $existingCategory->setSlugSQL($slugSQL);

                    $output->writeln('<info>Updated parent category: ' . $existingCategory->getName() . '</info>');
                } else {
                    // Création d'une nouvelle catégorie
                    $newCategory = new Category();
                    $newCategory->setId($id);
                    $newCategory->setParent(null); // Pas de parent
                    $newCategory->setCount($count);
                    $newCategory->setDescription($description);
                    $newCategory->setName($translations[0]->text);
                    $newCategory->setSlug($TransformSlug);
                    $newCategory->setSlugSQL($slugSQL);

                    $this->entityManager->persist($newCategory);

                    $output->writeln('<info>Created new parent category: ' . $newCategory->getName() . '</info>');
                }
            }
        }

        // Flusher les parents dans la base
        $this->entityManager->flush();
        $this->entityManager->clear(); // Clear the EntityManager to avoid duplication issues

        // Deuxième boucle : association des catégories enfants avec leurs parents
        foreach ($categories as $catData) {
            $id = $catData['id'] ?? null;
            $parentId = $catData['parent'] ?? null;
            $count = $catData['count'] ?? null;
            $description = $catData['description'] ?? '';
            $name = $catData['name'] ?? '';
            $slugSQL = $catData['slug'] ?? '';

            if (!$id) {
                continue; // On saute les catégories sans ID
            }

            // On s'occupe uniquement des catégories enfants (celles qui ont un parent)
            if ($parentId !== 0) {
                $existingCategory = $this->entityManager->getRepository(Category::class)->findOneBy(['id' => $id]);
                $parentCategory = $this->entityManager->getRepository(Category::class)->find($parentId);

                if (!$parentCategory) {
                    $output->writeln("<comment>Parent category with ID $parentId not found for category $id, skipping...</comment>");
                    continue;
                }
                $authKey = $_ENV['DEEPL_API_KEY'] ?? null;
                $translator = new \DeepL\Translator($authKey);
    
                $translations = $translator->translateText(
                    [
                        $name,
                    ],
                    null,
                    'en-GB',
                );

                $TransformSlug = $this->slugger->slug($translations[0]->text);

                if ($existingCategory) {
                    // Mise à jour de la catégorie existante
                    $existingCategory->setName($translations[0]->text);
                    $existingCategory->setParent($parentCategory);
                    $existingCategory->setCount($count);
                    $existingCategory->setDescription($description);
                    $existingCategory->setSlug($TransformSlug);
                    $existingCategory->setSlugSQL($slugSQL);

                    $output->writeln('<info>Updated child category: ' . $existingCategory->getName() . '</info>');
                } else {
                    // Création d'une nouvelle catégorie enfant
                    $newCategory = new Category();
                    $newCategory->setId($id);
                    $newCategory->setParent($parentCategory); // Assigner le parent
                    $newCategory->setCount($count);
                    $newCategory->setDescription($description);
                    $newCategory->setName($translations[0]->text);
                    $newCategory->setSlug($TransformSlug);
                    $newCategory->setSlugSQL($slugSQL);

                    $this->entityManager->persist($newCategory);

                    $output->writeln('<info>Created new child category: ' . $newCategory->getName() . '</info>');
                }
            }
        }

        // Flush des enfants dans la base
        $this->entityManager->flush();
        $this->entityManager->clear(); // Clear the EntityManager to free memory

        $output->writeln('<info>Categories have been updated successfully.</info>');

        return Command::SUCCESS;
    }

    /**
     * Extract pagination headers from HTTP headers.
     *
     * @param string[] $httpHeaders Array of HTTP headers.
     * @return array<string, int> Array containing pagination data like total pages or total items.
     */
    private function getPaginationHeaders(array $httpHeaders): array
    {
        // Extract pagination headers if available (specific to the API used)
        $paginationHeaders = [];
        foreach ($httpHeaders as $header) {
            if (preg_match('/X-WP-TotalPages:\s*(\d+)/i', $header, $matches)) {
                $paginationHeaders['X-WP-TotalPages'] = (int)$matches[1];
            }
            if (preg_match('/X-WP-Total:\s*(\d+)/i', $header, $matches)) {
                $paginationHeaders['X-WP-Total'] = (int)$matches[1];
            }
        }

        return $paginationHeaders;
    }
}
