<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    private const CATEGORIES = [
        'cinema' => 'cinema',
        'musique' => 'actualites-musique',
        'critiques' => 'critiques-musique',
        'evenements' => 'evenements-musique',
        'more' => 'manga-anime',
        'theatres' => 'theatre-scene',
        'dramas' => 'dramas',
        'voyage' => 'voyage',
        'tvshows' => 'tv-shows',
        'movies' => 'movies',
        'books' => 'books',
        'wanderlust' => 'wanderlust',
    ];

    public function __construct(
        private readonly ArticlesRepository $articleRepository
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $categorizedArticles = $this->getCategorizedArticles();

        $breaking = $this->articleRepository->findBy([], ['modified' => 'DESC'], 4);
        $slider = $this->articleRepository->findBy([], ['id' => 'DESC'], 4);
        $bestOf = $this->articleRepository->findBy([], ['visit' => 'DESC'], 4);
        $discover = $this->articleRepository->findBy([], ['visit' => 'DESC'], 4);
        $randomArticles = $this->articleRepository->RandomArticles();

        return $this->render('home/index.html.twig', array_merge([
            'breaking' => $breaking,
            'slider' => $slider,
            'bestOf' => $bestOf,
            'discover' => $discover,
            'randomArticles' => $randomArticles,
        ], $categorizedArticles));
    }

    /**
     * Fetch articles categorized by the defined categories.
     *
     * @return array
     */
    private function getCategorizedArticles(): array
    {
        $categorizedArticles = [];
        foreach (self::CATEGORIES as $key => $category) {
            try {
                $categorizedArticles[$key] = $this->articleRepository->findByCategory($category);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to fetch articles for category: ' . $category);
                $categorizedArticles[$key] = [];
            }
        }

        return $categorizedArticles;
    }
}