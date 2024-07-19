<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
        private readonly ArticlesRepository $article
    ){}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Fetch categorized articles
        $categorizedArticles = [];
        foreach (self::CATEGORIES as $key => $category) {
            $categorizedArticles[$key] = $this->article->findByCategory($category);
        }

        // Fetch common queries
        $breaking = $this->article->findBy([], ['modified' => 'DESC'], 4);
        $slider = $this->article->findBy([], ['id' => 'DESC'], 4);
        $bestof = $this->article->findBy([], ['visit' => 'DESC'], 4);
        $discover = $this->article->findBy([], ['visit' => 'DESC'], 4);
        $randomise = $this->article->RandomArticles();

        return $this->render('home/index.html.twig', array_merge([
            'breaking' => $breaking,
            'slider' => $slider,
            'bestof' => $bestof,
            'discover' => $discover,
            'randomise' => $randomise,
        ], $categorizedArticles));
    }
}
