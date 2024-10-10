<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;

class CategoryController extends AbstractController
{
    private ArticlesRepository $articlesRepository;
    private CategoryRepository $categoryRepository;

    public function __construct(ArticlesRepository $articlesRepository, CategoryRepository $categoryRepository)
    {
        $this->articlesRepository = $articlesRepository;
        $this->categoryRepository = $categoryRepository;
    }

    #[Route('/{categorie}/{slug}', name: 'app_sous_category', requirements: ['categorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 3)]
    #[Route('/{slug}', name: 'app_category', requirements: ['slug' => '[\w-]+'], priority: 5)]
    #[Cache(public: true, expires: '+1 hour')]
    public function index(string $slug, PaginatorInterface $paginator, Request $request): Response
    {
        // Récupération de la catégorie
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            return $this->render('bundles/TwigBundle/Exception/error404.html.twig', [], new Response('', 410));
        }

        // Gestion des catégories et sous-catégories
        $categories = $this->getCategoriesBasedOnRoute($category, $request);

        // Récupération des métadonnées
        [$title, $description] = $this->getCategoryMetaData($category);

        // Récupération des informations pour le fil d'Ariane
        [$breadcrumbCategoryName, $breadcrumbCategorySlug, $breadcrumbSousCategoryName, $breadcrumbSousCategorySlug] = $this->getBreadcrumbData($category, $request);

        // Pagination des articles
        $pagination = $this->paginateArticles($slug, $paginator, $request);

        // Rendu du template avec les données récupérées
        $response = $this->render('category/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'breadcrumbCategoryName' => $breadcrumbCategoryName,
            'breadcrumbCategorySlug' => $breadcrumbCategorySlug,
            'breadcrumbSousCategoryName' => $breadcrumbSousCategoryName,
            'breadcrumbSousCategorySlug' => $breadcrumbSousCategorySlug,
            'title' => $title,
            'description' => $description,
        ]);

        // Définir les en-têtes de mise en cache
        $response->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $response;
    }

    /**
     * Gestion des catégories et sous-catégories en fonction de la route.
     */
    private function getCategoriesBasedOnRoute($category, Request $request): array
    {
        if ('app_sous_category' === $request->attributes->get('_route')) {
            $parentCategory = $category->getParent();
            return $parentCategory ? $this->convertToArray($parentCategory->getSubcategories()) : [];
        }

        return $this->convertToArray($category->getSubcategories());
    }

    /**
     * Convertit une collection Doctrine ou un tableau en un tableau PHP.
     */
    private function convertToArray($collection): array
    {
        // Si c'est un objet de type Collection, le convertir en tableau
        if ($collection instanceof \Doctrine\Common\Collections\Collection) {
            return $collection->toArray();
        }

        // Sinon, si c'est déjà un tableau, le renvoyer tel quel
        return is_array($collection) ? $collection : [];
    }

    /**
     * Récupération du titre et de la description de la catégorie.
     */
    private function getCategoryMetaData($category): array
    {
        return [$category->getMetaTitle(), $category->getDescription()];
    }

    /**
     * Récupération des données pour le fil d'Ariane.
     */
    private function getBreadcrumbData($category, Request $request): array
    {
        $breadcrumbCategoryName = $category->getName();
        $breadcrumbCategorySlug = $category->getSlug();
        $breadcrumbSousCategoryName = null;
        $breadcrumbSousCategorySlug = null;

        if ('app_sous_category' === $request->attributes->get('_route')) {
            $parentCategory = $category->getParent();
            if ($parentCategory) {
                $breadcrumbSousCategoryName = $parentCategory->getName();
                $breadcrumbSousCategorySlug = $parentCategory->getSlug();
            }
        }

        return [$breadcrumbCategoryName, $breadcrumbCategorySlug, $breadcrumbSousCategoryName, $breadcrumbSousCategorySlug];
    }

    /**
     * Pagination des articles en fonction du slug de la catégorie.
     */
    private function paginateArticles(string $slug, PaginatorInterface $paginator, Request $request)
    {
        $articles = $this->articlesRepository->PaginationCategoryAndArticle($slug);

        return $paginator->paginate(
            $articles,
            $request->query->getInt('page', 1),
            10
        );
    }
}
