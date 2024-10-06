<?php

namespace App\Controller\SiteMap;

use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BingNewsController extends AbstractController
{
    private ArticlesRepository $articlesRepository;

    public function __construct(ArticlesRepository $articlesRepository)
    {
        $this->articlesRepository = $articlesRepository;
    }

    #[Route('/bing-news.xml', name: 'sitemap_bing_news', priority: 10)]
    public function bingNews(): Response
    {
        // Récupérer tous les articles récents en ligne
        $articles = $this->articlesRepository->findRecentOnlineArticles();

        // Initialisation du contenu XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">';
        $xml .= '<channel>';
        $xml .= '<title>The latest news from Just Focus</title>';
        $xml .= '<link>https://justfocus.info</link>';
        $xml .= '<description>JustFocus is the next-generation webzine that covers all your passions. Discover new articles on JustFocus every day!</description>';
        $xml .= '<language>en</language>';
        $xml .= '<lastBuildDate>' . date('D, d M Y H:i:s O') . '</lastBuildDate>';

        foreach ($articles as $article) {
            // Récupérer les catégories liées à cet article
            $categories = $article->getCategories();

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
                $formattedDate = null !== $date ? $date->format('D, d M Y H:i:s O') : '';

                // Échappement du titre et de la description de l'article
                $title = null !== $article->getTitle() ? htmlspecialchars($article->getTitle(), ENT_XML1, 'UTF-8') : '';
                $description = null !== $article->getMetaTitle() ? htmlspecialchars($article->getMetaTitle(), ENT_XML1, 'UTF-8') : '';

                // Ajout de l'article dans le flux RSS
                $xml .= '<item>';
                $xml .= '<title>' . $title . '</title>';
                $xml .= '<link>' . htmlspecialchars($url, ENT_XML1, 'UTF-8') . '</link>';
                $xml .= '<description>' . $description . '</description>';
                $xml .= '<pubDate>' . $formattedDate . '</pubDate>';

                // Ajout d'une image s'il y en a une
                if ($article->getMedia()) {
                    $xml .= '<media:thumbnail url="https://justfocus.info' . htmlspecialchars($article->getMedia()[0]->getGuid(), ENT_XML1, 'UTF-8') . '" />';
                }

                $xml .= '</item>';
            }
        }

        $xml .= '</channel>';
        $xml .= '</rss>';

        // Retourner la réponse sous forme de XML
        $response = new Response($xml);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
