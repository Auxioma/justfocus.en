<?php

namespace App\Controller\Admin;

use App\Entity\Articles;
use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GoogleSearchConsoleService;

class DashboardController extends AbstractDashboardController
{
    private $searchConsoleService;

    // Injecter le service GoogleSearchConsoleService
    public function __construct(GoogleSearchConsoleService $searchConsoleService)
    {
        $this->searchConsoleService = $searchConsoleService;
    }

    #[Route('/admin_olga150187', name: 'admin', priority: 10)]
    public function index(): Response
    {
        // Utiliser le service pour obtenir les données Google Search Console
        $siteUrl = 'https://justfocus.info'; // Remplace par ton URL
        $startDate = '2023-01-01';
        $endDate = '2024-09-31';

        // Récupérer les données via l'API
        $searchAnalytics = $this->searchConsoleService->getSearchAnalytics($siteUrl, $startDate, $endDate);
dd($searchAnalytics);
        // Passer les données à la vue
        return $this->render('admin/dashboard.html.twig', [
            'searchAnalytics' => $searchAnalytics,
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
