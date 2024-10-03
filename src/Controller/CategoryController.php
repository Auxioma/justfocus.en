<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class CategoryController extends AbstractController
{
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;
    private EntityManagerInterface $entityManager; // Add this

    public function __construct(ArticlesRepository $articlesRepository, CategoryRepository $categoryRepository, EntityManagerInterface $entityManager)
    {
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
        $this->entityManager = $entityManager; // Assign it here
    }

    #[Route('/{categorie}/{slug}', name: 'app_sous_category', requirements: ['categorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 3)]
    #[Route('/{slug}', name: 'app_category', requirements: ['slug' => '[\w-]+'], priority: 5)]
    public function index(string $slug, PaginatorInterface $paginator, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBySlug($slug);

        if ('app_sous_category' === $request->attributes->get('_route')) {
            $categories = $category->getParent()->getSubcategories();
        } else {
            $categories = $category->getSubcategories();
        }

        // affichage pour le breadcrumb
        if ('app_sous_category' === $request->attributes->get('_route')) {
            $breadcrumbSousCategoryName = $category->getParent()->getName();
            $breadcrumbSousCategorySlug = $category->getParent()->getSlug();

            $breadcrumbCategoryName = $category->getName();
            $breadcrumbCategorySlug = $category->getSlug();
        } else {
            $breadcrumbCategoryName = $category->getName();
            $breadcrumbCategorySlug = $category->getSlug();
        }

        $articles = $this->articlesRepository->PaginationCategoryAndArticle($slug);

        $pagination = $paginator->paginate(
            $articles,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('category/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'breadcrumbCategoryName' => $breadcrumbCategoryName,
            'breadcrumbCategorySlug' => $breadcrumbCategorySlug,
            'breadcrumbSousCategoryName' => $breadcrumbSousCategoryName ?? null,
            'breadcrumbSousCategorySlug' => $breadcrumbSousCategorySlug ?? null,
        ]);
    }

    #[Route('/{articlecategorie}/{souscategorie}/{slug}.html', name: 'app_articles', requirements: ['articlecategorie' => '[\w-]+', 'souscategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 2)]
    #[Route('/{articlecategorie}/{slug}.html', name: 'app_articles_without_souscategory', requirements: ['articlecategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 5)]
    public function articles(string $slug, SessionInterface $session): Response
    {
        // Récupérer l'article à partir du slug
        $article = $this->articlesRepository->findOneBy(['slug' => $slug, 'isOnline' => true]);

        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        // Vérifier si l'utilisateur a visité l'article au cours de la dernière heure
        $sessionKey = 'article_visit_'.$slug;
        $lastVisit = $session->get($sessionKey);

        $now = new \DateTime();
        $canIncrement = true;

        if ($lastVisit) {
            $lastVisitTime = new \DateTime($lastVisit);
            // Si la visite précédente remonte à moins d'une heure, ne pas incrémenter
            if ($now->getTimestamp() - $lastVisitTime->getTimestamp() < 3600) {
                $canIncrement = false;
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
