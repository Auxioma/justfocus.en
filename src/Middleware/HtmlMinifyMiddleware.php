<?php

// src/Middleware/HtmlMinifyMiddleware.php

namespace App\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class HtmlMinifyMiddleware implements HttpKernelInterface
{
    private HttpKernelInterface $httpKernel;

    public function __construct(HttpKernelInterface $httpKernel)
    {
        $this->httpKernel = $httpKernel;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        // On exécute d'abord la requête normalement
        $response = $this->httpKernel->handle($request, $type, $catch);

        // Vérifier si c'est du HTML
        if (str_contains($response->headers->get('Content-Type'), 'text/html')) {
            $content = $response->getContent();

            // Minifier le contenu HTML
            $minifiedContent = $this->minifyHtml($content);

            // Réassigner le contenu minifié à la réponse
            $response->setContent($minifiedContent);
        }

        return $response;
    }

    private function minifyHtml(string $html): string
    {
        return preg_replace(
            [
                '/\>[^\S ]+/s',
                '/[^\S ]+\</s',
                '/(\s)+/s',
                '/<!--(.*?)-->/s',
            ],
            ['>', '<', '\\1', ''],
            $html
        );
    }
}
