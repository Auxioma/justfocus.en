<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WordpressUserController extends AbstractController
{
    #[Route('/api/wordpress/user', name: 'app_api_wordpress_user')]
    public function index(): Response
    {
        return $this->render('api/wordpress_user/index.html.twig', [
            'controller_name' => 'WordpressUserController',
        ]);
    }
}
