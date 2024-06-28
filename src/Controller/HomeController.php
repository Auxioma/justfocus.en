<?php

namespace App\Controller;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly ArticlesRepository $article
    ){}
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        //dd($this->article->findBy([], ['id' => 'DESC'], 5));
        return $this->render('home/index.html.twig', [
            'breaking' => $this->article->findBy([], ['modified' => 'DESC'], 4),
            'slider'   => $this->article->findBy([], ['id' => 'DESC'], 4),
        ]);
    }
}
