<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    private ArticlesRepository $articlesRepository;
    private array $breaking;
    private array $slider;
    private array $cinema;
    private array $musique;
    private array $critiques;
    private array $evenements;
    private array $more;
    private array $theatres;
    private array $dramas;
    private array $voyage;
    private array $tvshows;
    private array $movies;
    private array $books;
    private array $wanderlust;
    private array $bestof;
    private array $discover;
    private array $randomise;

    public function __construct(ArticlesRepository $articlesRepository)
    {
        $this->articlesRepository = $articlesRepository;

        // Préchargement des données dans le constructeur
        $this->breaking = $this->articlesRepository->findBy([], ['modified' => 'DESC'], 4);
        $this->slider = $this->articlesRepository->findBy([], ['id' => 'DESC'], 4);
        $this->cinema = $this->articlesRepository->findByCategory('cinema');
        $this->bestof = $this->articlesRepository->findBy([], ['visit' => 'DESC'], 4);
        $this->discover = $this->articlesRepository->findBy([], ['visit' => 'ASC'], 10);
        $this->randomise = $this->articlesRepository->RandomArticles();
        
        $this->musique = $this->articlesRepository->findByCategory('actualites-musique');
        $this->critiques = $this->articlesRepository->findByCategory('critiques-musique');
        $this->evenements = $this->articlesRepository->findByCategory('evenements-musique');
        $this->more = $this->articlesRepository->findByCategory('manga-anime');
        $this->theatres = $this->articlesRepository->findByCategory('theatre-scene');
        $this->dramas = $this->articlesRepository->findByCategory('dramas');
        $this->voyage = $this->articlesRepository->findByCategory('voyage');
        $this->tvshows = $this->articlesRepository->findByCategory('tv-shows');
        $this->movies = $this->articlesRepository->findByCategory('movies');
        $this->books = $this->articlesRepository->findByCategory('books');
        $this->wanderlust = $this->articlesRepository->findByCategory('wanderlust');

    }

    #[Route('/', name: 'app_home')]
    #[Cache(smaxage: 3600, public: true)]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'breaking'   => $this->breaking,
            'slider'     => $this->slider,
            'cinema'     => $this->cinema,
            'musique'    => $this->musique,
            'critiques'  => $this->critiques,
            'evenements' => $this->evenements,
            'more'       => $this->more,
            'theatres'   => $this->theatres,
            'dramas'     => $this->dramas,
            'voyage'     => $this->voyage,
            'tvshows'    => $this->tvshows,
            'movies'     => $this->movies,
            'books'      => $this->books,
            'wanderlust' => $this->wanderlust,
            'bestof'     => $this->bestof,
            'discover'   => $this->discover,
            'randomise'  => $this->randomise,
        ]);
    }
}
