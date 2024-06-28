<?php

namespace App\Controller\Menu;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends AbstractController
{
    public function index(CategoryRepository $category): Response
    {
        return $this->render('_partials/header.html.twig', [
            'categories' => $category->findBy(['parent' => null, 'isOnline' => true])
        ]);
    }
}
