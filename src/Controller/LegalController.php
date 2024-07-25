<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LegalController extends AbstractController
{
    #[Route('/o/o/o/legal', name: 'legal', priority: 1)]
    public function index(): Response
    {
        return $this->render('legal/pricacy-policy.html.twig');
    }
}