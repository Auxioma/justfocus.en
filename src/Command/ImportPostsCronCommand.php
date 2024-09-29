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

    public function __construct(
        HttpClientInterface $client, // Injection de la dépendance pour le client HTTP
        ArticlesRepository $articlesRepository, // Injection de la dépendance pour le repository des articles
        CategoryRepository $categoryRepository, // Injection de la dépendance pour le repository des catégories
        UserRepository $userRepository, // Injection de la dépendance pour le repository des utilisateurs
        LoggerInterface $logger, // Injection de la dépendance pour le logger
        EntityManagerInterface $entityManager // Injection de la dépendance pour l'entity manager
    ) {
        parent::__construct(); // Appel au constructeur de la classe parente
        $this->client = $client; // Initialisation du client HTTP
        $this->articlesRepository = $articlesRepository; // Initialisation du repository des articles
        $this->categoryRepository = $categoryRepository; // Initialisation du repository des catégories
        $this->userRepository = $userRepository; // Initialisation du repository des utilisateurs
        $this->logger = $logger; // Initialisation du logger
        $this->entityManager = $entityManager; // Initialisation de l'entity manager
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Starting the import of posts from WordPress API...'); // Affiche un message de démarrage de l'importation

        $insertedCount = 0; // Compteur d'articles insérés
        $maxInserts = 50; // Nombre maximum d'insertions

            $page = 1; // Initialise la page à 1

            do {
                $url = self::WORDPRESS_API_URL . '?per_page=' . self::PER_PAGE . '&page=' . $page . '&status=publish'; // Construit l'URL pour la requête API
                $response = $this->client->request('GET', $url); // Fait la requête GET à l'API WordPress

                if ($response->getStatusCode() !== 200) { // Vérifie si la réponse n'est pas un succès
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
                            $output->writeln('Article with ID ' . $postData['id'] . ' already exists. Skipping...'); // Affiche un message si l'article existe déjà
                            continue; // Passe à l'itération suivante
                        }

                        $article = new Articles(); // Crée une nouvelle instance de l'entité Articles
                        $this->populateArticle($article, $postData); // Remplit l'article avec les données récupérées
                        $this->articlesRepository->save($article); // Sauvegarde l'article en base de données

                        $output->writeln('Article with ID ' . $postData['id'] . ' inserted.'); // Affiche un message de succès d'insertion
                        $insertedCount++; // Incrémente le compteur d'articles insérés
                    }
                    $this->entityManager->commit(); // Valide la transaction
                } catch (\Exception $e) { // Capture les exceptions
                    $this->entityManager->rollback(); // Annule la transaction en cas d'erreur
                    $this->logger->error('Failed to process posts: ' . $e->getMessage()); // Log l'erreur
                    $output->writeln('Error: ' . $e->getMessage()); // Affiche l'erreur
                    return Command::FAILURE; // Retourne un échec de commande
                }

                $page++; // Passe à la page suivante
            } while (count($InsertPost) === self::PER_PAGE); // Continue tant qu'il y a des articles à récupérer
        

        $output->writeln('Articles successfully inserted.'); // Affiche un message de succès
        return Command::SUCCESS; // Retourne un succès de commande
    }

    private function populateArticle(Articles $article, array $postData): void
    {
        $article->setId($postData['id']); // Définit l'ID de l'article
        $article->setTitle(html_entity_decode($postData['title']['rendered'] ?? 'No title')); // Définit le titre de l'article
        $article->setSlug(html_entity_decode($postData['slug'] ?? '')); // Définit le slug de l'article
        $article->setDate(new \DateTime($postData['date'] ?? 'now')); // Définit la date de l'article
        $article->setModified(new \DateTime($postData['modified'] ?? 'now')); // Définit la date de modification de l'article
        $article->setContent(html_entity_decode($postData['content']['rendered'] ?? '')); // Définit le contenu de l'article
        $article->setMetaTitle($postData['title']['rendered'] ?? 'No title'); // Définit le titre de la méta
 
        // Meta description avec nettoyage et gestion des balises HTML
        $metaDescription = html_entity_decode(strip_tags($postData['excerpt']['rendered'] ?? ''));
        if (!empty($metaDescription)) {
            $metaDescription = $this->truncateDescription($metaDescription, 250);
        }

        $article->setMetaDescription($metaDescription);

        foreach ($postData['categories'] ?? [] as $catId) { // Boucle sur les catégories de l'article
            $category = $this->categoryRepository->find($catId); // Cherche la catégorie par ID
            if ($category) { // Si la catégorie est trouvée
                $article->addCategory($category); // Ajoute la catégorie à l'article
            }
        }

        $userId = $postData['author'] ?? null; // Récupère l'ID de l'auteur
        if ($userId) { // Si l'ID de l'auteur existe
            $user = $this->userRepository->find($userId); // Cherche l'utilisateur par ID
            if ($user) { // Si l'utilisateur est trouvé
                $article->setUser($user); // Associe l'utilisateur à l'article
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
        if ($lastSpace !== false) {
            return substr($truncated, 0, $lastSpace) . '...';
        }

        // Sinon, renvoie la description tronquée sans espace trouvé
        return substr($truncated, 0, $maxLength) . '...';
    }

}