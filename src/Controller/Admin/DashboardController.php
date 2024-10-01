<?php

namespace App\Controller\Admin;

use App\Entity\Articles;
use App\Entity\Category;

use App\Service\GoogleSearchConsoleService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

class DashboardController extends AbstractDashboardController
{   
    
    private $googleService;

    public function __construct(GoogleSearchConsoleService $googleService)
    {
        $this->googleService = $googleService;
    }

    #[Route('/admin_olga150187', name: 'admin', priority: 10)]
    public function index(): Response
    {
        /*try {
            // Essayer de récupérer les données de Google Search Console
            $searchConsoleData = $this->googleService->getSearchConsoleData();
        
        } catch (\Exception $e) {
            // Si l'utilisateur n'est pas authentifié, rediriger vers Google pour autorisation
            if ($e->getMessage() === 'Aucun jeton d\'accès disponible.') {
                return $this->redirect($this->googleService->getAuthUrl());
            }

            throw $e; // Lancer d'autres types d'erreurs
        }*/

        // Si tout est bon, afficher les données
        return $this->render('bundles/EasyAdminBundle/page/login.html.twig', [
            
        ]);  
    }


    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Justfocus');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Categorie', 'fas fa-list', Category::class);
        yield MenuItem::linkToCrud('Articles en francais', 'fas fa-list', Articles::class)->setQueryParameter('isOnline', false);
        yield MenuItem::linkToCrud('Articles en anglais', 'fas fa-list', Articles::class)->setQueryParameter('isOnline', true);
    }
}
