<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    public function index(string $slug, PaginatorInterface $paginator, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('Category not found');
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

        // Paginate articles
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
}
