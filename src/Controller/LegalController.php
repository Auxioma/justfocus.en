<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LegalController extends AbstractController
{
    #[Route('/private-policy', name: 'app_legal', priority: 10)]
    public function index(): Response
    {
        return $this->render('legal/pricacy-policy.html.twig');
    }

    #[Route('/terms-and-conditions', name: 'terms-and-conditions', priority: 10)]
    public function therm(): Response
    {
        return $this->render('legal/terms-conditions.html.twig');
    }
}
