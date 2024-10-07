<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Attribute\Cache;

class LegalController extends AbstractController
{
    #[Route('/private-policy', name: 'app_legal', priority: 10)]
    #[cache(public: true, expires: '+1 hour')]
    public function index(): Response
    {
        $template =  $this->render('legal/pricacy-policy.html.twig');

        $template->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $template; 
    }

    #[Route('/terms-and-conditions', name: 'terms-and-conditions', priority: 10)]
    #[cache(public: true, expires: '+1 hour')]
    public function therm(): Response
    {
        $template =  $this->render('legal/terms-conditions.html.twig');

        $template->headers->set('Cache-Control', 'public, max-age=3600, must-revalidate');

        return $template; 
    }
}
