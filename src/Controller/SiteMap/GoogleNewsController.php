<?php

namespace App\Controller\SiteMap;

use App\Repository\ArticlesRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

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

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';

        foreach ($articles as $article) {
            // Fetch categories related to this article
            $categories = $article->getCategories();
            $url = '';

            // Generate URL based on category/subcategory structure
            if (count($categories) > 0) {
                // If there are multiple categories, take the first one as the main category
                $category = $categories[0];
                
                if ($category->getParent()) {
                    // If there is a parent category (subcategory), create URL with both category and subcategory
                    $url = 'https://justfocus.info/' . $category->getParent()->getSlug() . '/' . $category->getSlug() . '/' . $article->getSlug();
                } else {
                    // Otherwise, only the category exists without a subcategory
                    $url = 'https://justfocus.info/' . $category->getSlug() . '/' . $article->getSlug();
                }
            }

            // Add the article information to the XML sitemap
            $xml .= '<url>';
            $xml .= '<loc>' . $url . '</loc>';
            $xml .= '<news:news>';
            $xml .= '<news:publication>';
            $xml .= '<news:name>Just Focus</news:name>';
            $xml .= '<news:language>en</news:language>';
            $xml .= '</news:publication>';
            $xml .= '<news:publication_date>' . $article->getDate()->format('Y-m-d') . '</news:publication_date>';
            $xml .= '<news:title>' . htmlspecialchars($article->getTitle(), ENT_XML1, 'UTF-8') . '</news:title>';
            $xml .= '</news:news>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
