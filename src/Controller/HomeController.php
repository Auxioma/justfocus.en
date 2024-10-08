<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
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
        private readonly ArticlesRepository $articleRepository,
    ) {
    }

    #[Route('/', name: 'app_home')]
    #[cache(public: true, expires: '+1 hour')]
    public function index(): Response
    {
        // Fetch categorized articles
        $categorizedArticles = array_map(
            fn ($category) => $this->articleRepository->findByCategory($category),
            self::CATEGORIES
        );

        // Fetch common queries
        $breaking = $this->articleRepository->findBy(['isOnline' => true], ['modified' => 'DESC'], 4);
        $slider = $this->articleRepository->findBy(['isOnline' => true], ['id' => 'DESC'], 4);
        $bestofAndDiscover = $this->articleRepository->findBy(['isOnline' => true], ['visit' => 'DESC'], 4);
        $randomise = $this->articleRepository->RandomArticles();

        $template =  $this->render('home/index.html.twig', array_merge([
            'breaking' => $breaking,
            'slider' => $slider,
            'bestof' => $bestofAndDiscover,
            'discover' => $bestofAndDiscover,
            'randomise' => $randomise,
        ], $categorizedArticles));

        $template->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $template; 
    }
}
