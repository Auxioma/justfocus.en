<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LegalController extends AbstractController
{
<<<<<<< HEAD
    #[Route('/o/o/o/legal', name: 'legal', priority: 1)]
=======
    #[Route('/legal', name: 'legal', priority: 1)]
>>>>>>> 9aa4e7fdbbe9b9961afc87c5e8886015aea83af5
    public function index(): Response
    {
        return $this->render('legal/pricacy-policy.html.twig');
    }
}