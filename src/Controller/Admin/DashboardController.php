<?php

namespace App\Controller\Admin;

use App\Entity\Articles;
use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin_olga150187', name: 'admin', priority: 10)]
    public function index(): Response
    {

        return $this->render('EasyAdminBundle/page/login.html.twig');
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
