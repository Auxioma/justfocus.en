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
    #[cache(public: true, expires: '+1 hour')]
    public function index(string $slug, PaginatorInterface $paginator, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            return $this->render('bundles/TwigBundle/Exception/error404.html.twig', [], new Response('', 410));
        }

        // Categories: check if the parent exists before accessing its subcategories
        if ('app_sous_category' === $request->attributes->get('_route')) {
            $parentCategory = $category->getParent();
            if ($parentCategory) {
                $categories = $parentCategory->getSubcategories();
            } else {
                $categories = []; // or handle the case where there is no parent
            }
        } else {
            $categories = $category->getSubcategories();
        }

        // Breadcrumb logic with null checks
        $breadcrumbCategoryName = $category->getName();
        $breadcrumbCategorySlug = $category->getSlug();

        if ('app_sous_category' === $request->attributes->get('_route')) {
            $parentCategory = $category->getParent();
            if ($parentCategory) {
                $breadcrumbSousCategoryName = $parentCategory->getName();
                $breadcrumbSousCategorySlug = $parentCategory->getSlug();
            } else {
                $breadcrumbSousCategoryName = null;
                $breadcrumbSousCategorySlug = null;
            }
        }

        //  je vais récupéré le titre et la description de la catégorie ou la sous catégorie
        if ('app_sous_category' === $request->attributes->get('_route')) {
            $title = $category->getMetaTitle();
            $description = $category->getDescription();
        }

        if ('app_category' === $request->attributes->get('_route')) {
            $title = $category->getMetaTitle();
            $description = $category->getDescription();
        }

        // Paginate articles
        $articles = $this->articlesRepository->PaginationCategoryAndArticle($slug);

        $pagination = $paginator->paginate(
            $articles,
            $request->query->getInt('page', 1),
            10
        );

        $template = $this->render('category/index.html.twig', [
            'pagination' => $pagination,
            'categories' => $categories,
            'breadcrumbCategoryName' => $breadcrumbCategoryName,
            'breadcrumbCategorySlug' => $breadcrumbCategorySlug,
            'breadcrumbSousCategoryName' => $breadcrumbSousCategoryName ?? null,
            'breadcrumbSousCategorySlug' => $breadcrumbSousCategorySlug ?? null,
            'title' => $title,
            'description' => $description,
        ]);

        $template->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $template; 
    }
}
