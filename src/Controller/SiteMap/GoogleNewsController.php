<?php

namespace App\Controller\SiteMap;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class GoogleNewsController extends AbstractController
{
    private ArticlesRepository $articlesRepository;

    public function __construct(ArticlesRepository $articlesRepository)
    {
        $this->articlesRepository = $articlesRepository;
    }

    #[Route('/google-news.xml', name: 'sitemap_google_news', priority: 10)]
    public function googleNews(): Response
    {
        // Récupérer tous les articles récents en ligne
        $articles = $this->articlesRepository->findRecentOnlineArticles();

        // Initialisation du contenu XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

        foreach ($articles as $article) {
            // Récupérer les catégories liées à cet article
            $categories = $article->getCategories();

            // L'article peut avoir plusieurs URLs en fonction de ses catégories
            foreach ($categories as $category) {
                $url = '';

                // Générer l'URL basée sur la structure catégorie/sous-catégorie
                if (null !== $category && $category->getParent()) {
                    $url = 'https://justfocus.info/' . $category->getParent()->getSlug() . '/' . $category->getSlug() . '/' . $article->getSlug() . '.html';
                } elseif (null !== $category) {
                    $url = 'https://justfocus.info/' . $category->getSlug() . '/' . $article->getSlug() . '.html';
                }

                // Formatage de la date de publication de l'article
                $date = $article->getDate();
                $formattedDate = null !== $date ? $date->format('Y-m-d') : '';

                // Échappement du titre de l'article
                $title = null !== $article->getTitle() ? htmlspecialchars($article->getTitle(), ENT_XML1, 'UTF-8') : '';

                // Ajouter l'information de l'article au sitemap
                $xml .= '<url>';
                $xml .= '<loc>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</loc>';
                $xml .= '<news:news>';
                $xml .= '<news:publication>';
                $xml .= '<news:name>Just Focus</news:name>';
                $xml .= '<news:language>en</news:language>';
                $xml .= '</news:publication>';
                $xml .= '<news:publication_date>' . $formattedDate . '</news:publication_date>';
                $xml .= '<news:title>' . $title . '</news:title>';
                $xml .= '</news:news>';
                $xml .= '</url>';
            }
        }

        $xml .= '</urlset>';

        // Retourner la réponse sous forme de XML
        $response = new Response($xml);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
