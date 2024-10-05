<?php

namespace App\Controller\SiteMap;

use App\Entity\Articles;
use App\Repository\ArticlesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ImageSitemapController extends AbstractController
{
    private ArticlesRepository $articlesRepository;

    public function __construct(ArticlesRepository $articlesRepository)
    {
        $this->articlesRepository = $articlesRepository;
    }

    #[Route('/image-sitemap.xml', name: 'sitemap_images', priority: 10)]
    public function imageSitemap(): Response
    {
        // Fetch all recent online articles
        $articles = $this->articlesRepository->findBy(['isOnline' => true]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

        foreach ($articles as $article) {
            // Fetch categories related to this article to construct the article URL
            $categories = $article->getCategories();
            $url = '';

            // Generate URL based on category/subcategory structure
            if (count($categories) > 0) {
                $category = $categories[0];

                if ($category && $category->getParent()) {
                    $url = 'https://justfocus.info/'.$category->getParent()->getSlug().'/'.$category->getSlug().'/'.$article->getSlug().'.html';
                } elseif ($category) {
                    $url = 'https://justfocus.info/'.$category->getSlug().'/'.$article->getSlug().'.html';
                } else {
                    $url = 'https://justfocus.info/'.$article->getSlug().'.html';
                }
            } else {
                $url = 'https://justfocus.info/'.$article->getSlug().'.html';
            }

            // Start building the XML for this URL
            $xml .= '<url>';
            $xml .= '<loc>'.$url.'</loc>';

            // Fetch and append article images
            $mediaItems = $article->getMedia(); // Assuming Media entity has an image URL
            foreach ($mediaItems as $media) {
                $imageUrl = $media->getGuid(); // Assuming Media entity has getUrl() method
                if ($imageUrl) {
                    $xml .= '<image:image>';
                    $xml .= '<image:loc>https://justfocus.info'.$imageUrl.'</image:loc>';

                    // Ensure title and caption are strings and not null
                    $articleTitle = $article->getTitle() ?: '';
                    $mediaTitle = $media->getTitle() ?: '';

                    $xml .= '<image:title>'.htmlspecialchars($articleTitle, ENT_XML1, 'UTF-8').'</image:title>';
                    $xml .= '<image:caption>'.htmlspecialchars($mediaTitle, ENT_XML1, 'UTF-8').'</image:caption>';
                    $xml .= '</image:image>';
                }
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        // Return the response as XML
        $response = new Response($xml);
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }
}
