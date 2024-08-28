<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use App\Repository\CategoryRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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
        $category = $this->categoryRepository->findOneBySlug($slug);
        
        if ($request->attributes->get('_route') === 'app_sous_category') {
            $categories = $category->getParent()->getSubcategories();
        } else {
            $categories = $category->getSubcategories();
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
        ]);
    }

    #[Route('/{articlecategorie}/{souscategorie}/{slug}', name: 'app_articles', requirements: ['articlecategorie' => '[\w-]+', 'souscategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 2)]
    #[Route('/{articlecategorie}/{slug}', name: 'app_articles_without_souscategory', requirements: ['articlecategorie' => '[\w-]+', 'slug' => '[\w-]+'], priority: 3)]
    public function articles(string $slug): Response
    {
        return $this->render('category/articles.html.twig', [
            'article' => $this->articlesRepository->findOneBySlug($slug),
            'categories' => $this->categoryRepository->findBy(['parent' => null, 'isOnline' => true])
        ]);
    }
}
