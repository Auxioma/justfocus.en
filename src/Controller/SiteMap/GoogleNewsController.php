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
        $articles = $this->articlesRepository->findRecentOnlineArticles();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

        foreach ($articles as $article) {
            // Fetch categories related to this article
            $categories = $article->getCategories();
            $url = '';

            // Generate URL based on category/subcategory structure
            if (count($categories) > 0 && null !== $categories[0]) {
                $category = $categories[0];

                if (null !== $category && $category->getParent()) {
                    $url = 'https://justfocus.info/'.$category->getParent()->getSlug().'/'.$category->getSlug().'/'.$article->getSlug().'.html';
                } elseif (null !== $category) {
                    $url = 'https://justfocus.info/'.$category->getSlug().'/'.$article->getSlug().'.html';
                }
            }

            // Vérifier la date avant d'appeler format()
            $date = $article->getDate();
            $formattedDate = null !== $date ? $date->format('Y-m-d') : '';

            // Vérifier le titre avant d'utiliser htmlspecialchars()
            $title = null !== $article->getTitle() ? htmlspecialchars($article->getTitle(), ENT_XML1, 'UTF-8') : '';

            // Add the article information to the XML sitemap
            $xml .= '<url>';
            $xml .= '<loc>'.$url.'</loc>';
            $xml .= '<news:news>';
            $xml .= '<news:publication>';
            $xml .= '<news:name>Just Focus</news:name>';
            $xml .= '<news:language>en</news:language>';
            $xml .= '</news:publication>';
            $xml .= '<news:publication_date>'.$formattedDate.'</news:publication_date>';
            $xml .= '<news:title>'.$title.'</news:title>';
            $xml .= '</news:news>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
