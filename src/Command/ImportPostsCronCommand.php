<?php

namespace App\Command;

use App\Entity\Articles;
use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-posts', // Nom de la commande dans la console Symfony
    description: 'Imports posts from a WordPress API and stores them in the database' // Description de la commande
)]
final class ImportPostsCronCommand extends Command
{
    private const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts'; // URL de l'API WordPress à utiliser
    private const PER_PAGE = 100; // Nombre d'articles à récupérer par page

    private HttpClientInterface $client; // Interface pour faire des requêtes HTTP
    private ArticlesRepository $articlesRepository; // Repository pour les articles
    private CategoryRepository $categoryRepository; // Repository pour les catégories
    private UserRepository $userRepository; // Repository pour les utilisateurs
    private LoggerInterface $logger; // Interface pour le logging
    private EntityManagerInterface $entityManager; // Interface pour la gestion des entités
    private SluggerInterface $slugger; // Interface pour la gestion des slugs

    public function __construct(
        HttpClientInterface $client, // Injection de la dépendance pour le client HTTP
        ArticlesRepository $articlesRepository, // Injection de la dépendance pour le repository des articles
        CategoryRepository $categoryRepository, // Injection de la dépendance pour le repository des catégories
        UserRepository $userRepository, // Injection de la dépendance pour le repository des utilisateurs
        LoggerInterface $logger, // Injection de la dépendance pour le logger
        EntityManagerInterface $entityManager, // Injection de la dépendance pour l'entity manager
        SluggerInterface $slugger, // Injection de la dépendance pour le slugger
    ) {
        parent::__construct(); // Appel au constructeur de la classe parente
        $this->client = $client; // Initialisation du client HTTP
        $this->articlesRepository = $articlesRepository; // Initialisation du repository des articles
        $this->categoryRepository = $categoryRepository; // Initialisation du repository des catégories
        $this->userRepository = $userRepository; // Initialisation du repository des utilisateurs
        $this->logger = $logger; // Initialisation du logger
        $this->entityManager = $entityManager; // Initialisation de l'entity manager
        $this->slugger = $slugger; // Initialisation du slugger
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting the import of posts from WordPress API...'); // Affiche un message de démarrage de l'importation

        $insertedCount = 0; // Compteur d'articles insérés
        $maxInserts = 500000; // Nombre maximum d'insertions

        $page = 1; // Initialise la page à 1

        do {
            $url = self::WORDPRESS_API_URL.'?per_page='.self::PER_PAGE.'&page='.$page.'&status=publish'; // Construit l'URL pour la requête API
            $response = $this->client->request('GET', $url); // Fait la requête GET à l'API WordPress

            if (200 !== $response->getStatusCode()) { // Vérifie si la réponse n'est pas un succès
                $this->logger->error('Une erreur s\'est produite lors de la récupération des articles.'); // Log l'erreur
                break; // Sort de la boucle
            }

            $InsertPost = $response->toArray(); // Convertit la réponse en tableau PHP
            if (empty($InsertPost)) { // Vérifie si la réponse est vide
                break; // Sort de la boucle
            }

            $this->entityManager->beginTransaction(); // Démarre une transaction
            try {
                foreach ($InsertPost as $postData) { // Boucle sur chaque article de la catégorie
                    if ($insertedCount >= $maxInserts) { // Vérifie si le nombre maximum d'insertions est atteint
                        $output->writeln('Maximum number of articles inserted. Ending import.'); // Affiche un message d'arrêt de l'importation
                        $this->entityManager->commit(); // Valide la transaction

                        return Command::SUCCESS; // Retourne un succès de commande
                    }

                    $existingArticle = $this->articlesRepository->find($postData['id']); // Cherche si l'article existe déjà

                    if ($existingArticle) { // Vérifie si l'article existe

                        // Je vais créer une fonction pour vérifier les relation entre les article et les catégories
                        // date du jour
                        $this->verifyArticleCategory($existingArticle, $postData, $output);
                        $output->writeln('Article with ID '.$postData['id'].' already exists. Skipping...'); // Affiche un message si l'article existe déjà
                        continue; // Passe à l'itération suivante
                    }

                    $article = new Articles(); // Crée une nouvelle instance de l'entité Articles
                    $this->populateArticle($article, $postData); // Remplit l'article avec les données récupérées
                    $this->articlesRepository->save($article); // Sauvegarde l'article en base de données

                    $output->writeln('Article with ID '.$postData['id'].' inserted.'); // Affiche un message de succès d'insertion
                    ++$insertedCount; // Incrémente le compteur d'articles insérés
                }
                $this->entityManager->commit(); // Valide la transaction
            } catch (\Exception $e) { // Capture les exceptions
                $this->entityManager->rollback(); // Annule la transaction en cas d'erreur
                $this->logger->error('Failed to process posts: '.$e->getMessage()); // Log l'erreur
                $output->writeln('Error: '.$e->getMessage()); // Affiche l'erreur

                return Command::FAILURE; // Retourne un échec de commande
            }

            ++$page; // Passe à la page suivante
        } while (self::PER_PAGE === count($InsertPost)); // Continue tant qu'il y a des articles à récupérer

        $output->writeln('Articles successfully inserted.'); // Affiche un message de succès

        return Command::SUCCESS; // Retourne un succès de commande
    }

    /**
     * @param array<string, mixed> $postData The data retrieved from the WordPress API
     * @param Articles             $article  The article entity to populate
     */
    private function populateArticle(Articles $article, array $postData): void
    {
        $authKey = $_ENV['DEEPL_API_KEY'] ?? null;
        $translator = new \DeepL\Translator($authKey);

        // Check if $postData['title'] is an array and contains 'rendered'
        $title = (isset($postData['title']) && is_array($postData['title']) && isset($postData['title']['rendered']) && is_string($postData['title']['rendered']))
            ? $postData['title']['rendered']
            : 'No title'; // Fallback value if not found or invalid

        // Check if $postData['excerpt'] is an array and contains 'rendered'
        $excerpt = (isset($postData['excerpt']) && is_array($postData['excerpt']) && isset($postData['excerpt']['rendered']) && is_string($postData['excerpt']['rendered']))
            ? $postData['excerpt']['rendered']
            : ''; // Fallback value if not found or invalid

        // Translate the title and excerpt
        $translations = $translator->translateText(
            [
                html_entity_decode($title),
                $title,
                html_entity_decode(strip_tags($excerpt)),
            ],
            null,
            'en-GB',
        );

        // Set article ID, ensuring it's an integer
        if (isset($postData['id']) && is_int($postData['id'])) {
            $article->setId($postData['id']);
        } else {
            // Handle missing or invalid ID
            throw new \Exception('Invalid or missing article ID');
        }

        // Set article title from translation
        $article->setTitle($translations[0]->text);

        // Check if $postData['slug'] is a string
        $slug = (isset($postData['slug']) && is_string($postData['slug'])) ? $postData['slug'] : '';
        $slug = $this->slugger->slug(html_entity_decode($translations[0]->text));
        $article->setSlug(html_entity_decode($slug));

        // Check if $postData['date'] is a valid string
        $date = (isset($postData['date']) && is_string($postData['date'])) ? $postData['date'] : 'now';
        $article->setDate(new \DateTime($date));

        // Check if $postData['modified'] is a valid string
        $modifiedDate = (isset($postData['modified']) && is_string($postData['modified'])) ? $postData['modified'] : 'now';
        $article->setModified(new \DateTime($modifiedDate));

        // Check if $postData['content'] is an array and contains 'rendered'
        $content = (isset($postData['content']) && is_array($postData['content']) && isset($postData['content']['rendered']) && is_string($postData['content']['rendered']))
            ? $postData['content']['rendered']
            : ''; // Fallback if not found
        $article->setContent(html_entity_decode($content));

        // Set meta title from translation
        $article->setMetaTitle($translations[1]->text);

        // Meta description, truncate if necessary
        $metaDescription = $translations[2]->text;
        if (!empty($metaDescription)) {
            $metaDescription = $this->truncateDescription($metaDescription, 250);
        }
        $article->setMetaDescription($metaDescription);

        // Set categories, ensure $postData['categories'] is iterable
        if (isset($postData['categories']) && is_array($postData['categories'])) {
            foreach ($postData['categories'] as $catId) {
                if (is_int($catId)) {
                    $category = $this->categoryRepository->find($catId);
                    if ($category) {
                        $article->addCategory($category);
                    }
                }
            }
        }

        // Set author (user), ensure $postData['author'] exists and is valid
        $userId = $postData['author'] ?? null;
        if (is_int($userId)) {
            $user = $this->userRepository->find($userId);
            if ($user) {
                $article->setUser($user);
            }
        }
    }

    /**
     * Tronque la description sans couper les mots et en respectant les espaces.
     */
    private function truncateDescription(string $description, int $maxLength): string
    {
        if (strlen($description) <= $maxLength) {
            return $description;
        }

        $truncated = substr($description, 0, $maxLength);
        // Trouve le dernier espace pour éviter de couper un mot
        $lastSpace = strrpos($truncated, ' ');

        // Si un espace est trouvé, on coupe à cet endroit
        if (false !== $lastSpace) {
            return substr($truncated, 0, $lastSpace).'...';
        }

        // Sinon, renvoie la description tronquée sans espace trouvé
        return substr($truncated, 0, $maxLength).'...';
    }

    private function verifyArticleCategory(Articles $article, array $postData, OutputInterface $output): void
    {
        // Set categories, ensure $postData['categories'] is iterable
        if (isset($postData['categories']) && is_array($postData['categories'])) {
            foreach ($postData['categories'] as $catId) {
                if (is_int($catId)) {
                    $category = $this->categoryRepository->find($catId);
    
                    if ($category) {
                        // Vérifie si l'article est déjà lié à la catégorie
                        if ($article->getCategories()->contains($category)) {
                            $output->writeln('Relation between article ID '.$article->getId().' and category ID '.$catId.' already exists.');
                        } else {
                            // Ajoute la nouvelle relation entre l'article et la catégorie
                            $article->addCategory($category);
                            $output->writeln('<fg=green>New relation created between article ID '.$article->getId().' and category ID '.$catId.'.</>');
    
                            // Persist the changes in EntityManager
                            $this->entityManager->persist($article);
                            $this->entityManager->flush();
                        }
                    }
                }
            }  
        }
    }
    
    
}
