<?php

namespace App\Controller\SiteMap;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ArticlesController extends AbstractController
{
    #[Route('/site/map/articles', name: 'app_site_map_articles')]
    public function index(): Response
    {
        return $this->render('site_map/articles/index.html.twig', [
            'controller_name' => 'ArticlesController',
        ]);
    }
}
