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

    #[Route('/{category}/{slug}', name: 'app_sous_category')]
    #[Route('/{slug}', name: 'app_category')]
    public function index(string $slug, PaginatorInterface $paginator, Request $request): Response
    {
        $category = $this->categoryRepository->findOneBySlug($slug);
        
        if ($request->attributes->get('_route') === 'app_sous_category') {
            $categories = $category->getParent()->getSubcategories();
        } else {
            $categories = $category->getSubcategories();
        }

        $articles = $this->articlesRepository->findByCategory($slug);

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

    #[Route('/{categorie}/{souscategorie}/{slug}', name: 'app_articles')]
    #[Route('/{categorie}/{slug}', name: 'app_articles_without_souscategory')]
    public function articles(): Response
    {
        return $this->render('category/articles.html.twig');
    }
}
