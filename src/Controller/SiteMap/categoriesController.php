<?php

namespace App\Controller\SiteMap;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class categoriesController extends AbstractController
{
    #[Route('/creation-sitemap-tous-les-jours', name: 'generate_sitemaps', priority: 10)]
    public function generateSitemaps(CategoryRepository $categoryRepository): Response
    {
        // Récupérer les catégories en ligne uniquement
        $categories = $categoryRepository->findBy(['isOnline' => true]);

        $filesystem = new Filesystem();
        $sitemapsDir = $this->getParameter('kernel.project_dir').'/public/sitemaps/';

        // Créer le dossier "sitemaps" s'il n'existe pas
        if (!$filesystem->exists($sitemapsDir)) {
            $filesystem->mkdir($sitemapsDir);
        }

        // Boucler sur chaque catégorie pour créer un fichier sitemap
        foreach ($categories as $category) {
            // Vérifie si la catégorie a un parent et génère l'URL correctement
            if ($category->getParent()) {
                $urlPath = $category->getParent()->getSlug().'/'.$category->getSlug();
            } else {
                $urlPath = $category->getSlug();
            }

            $filename = $sitemapsDir.$category->getSlug().'.xml';
            $xmlContent = $this->renderView('site_map/categories/index.html.twig', [
                'category' => $category,
                'urlPath' => $urlPath, // Passe l'URL personnalisée à la vue
            ]);

            // Écrire le fichier XML pour chaque catégorie
            $filesystem->dumpFile($filename, $xmlContent);
        }

        return new Response('Sitemaps generated successfully.');
    }
}
