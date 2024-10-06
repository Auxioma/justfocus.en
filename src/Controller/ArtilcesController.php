<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class ArtilcesController extends AbstractController
{
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(ArticlesRepository $articlesRepository, CategoryRepository $categoryRepository, EntityManagerInterface $entityManager)
    {
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/{articlecategorie}/{souscategorie}/{slug}.html', name: 'app_articles', requirements: ['articlecategorie' => '[\w-]+', 'souscategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 2)]
    #[Route('/{articlecategorie}/{slug}.html', name: 'app_articles_without_souscategory', requirements: ['articlecategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 5)]
    public function articles(string $slug, SessionInterface $session): Response
    {
        // Récupérer l'article à partir du slug
        $article = $this->articlesRepository->findOneBy(['slug' => $slug, 'isOnline' => true]);

        if (!$article) {
            return $this->render('bundles/TwigBundle/Exception/error404.html.twig', [], new Response('', 410));
        }

        // Vérifier si l'utilisateur a visité l'article au cours de la dernière heure
        $sessionKey = 'article_visit_'.$slug;
        $lastVisit = $session->get($sessionKey);

        $now = new \DateTime();
        $canIncrement = true;

        if ($lastVisit && is_string($lastVisit)) {
            // Ensure $lastVisit is a valid string before passing to DateTime
            try {
                $lastVisitTime = new \DateTime($lastVisit);
            } catch (\Exception $e) {
                $lastVisitTime = null; // Handle invalid date format, ignore last visit
            }

            if ($lastVisitTime instanceof \DateTime) {
                // Si la visite précédente remonte à moins d'une heure, ne pas incrémenter
                if ($now->getTimestamp() - $lastVisitTime->getTimestamp() < 3600) {
                    $canIncrement = false;
                }
            }
        }

        if ($canIncrement) {
            // Incrémenter le compteur de visites
            $article->incrementVisit();
            $this->entityManager->persist($article);
            $this->entityManager->flush();

            // Mettre à jour la session avec l'heure actuelle de visite
            $session->set($sessionKey, $now->format('Y-m-d H:i:s'));
        }

        // Rendre la vue avec l'article et les catégories
        return $this->render('category/articles.html.twig', [
            'article' => $article,
            'categories' => $this->categoryRepository->findBy(['parent' => null, 'isOnline' => true]),
        ]);
    }
}
