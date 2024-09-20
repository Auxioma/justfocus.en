<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpFoundation\Request;
use Throwable;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ErrorController
{
    #[Route('/error', name: 'error_show', priority: 10)]
    public function show(Request $request, Throwable $exception = null): Response
    {
        // Si l'exception est une instance de HttpExceptionInterface et que c'est une erreur 404
        if ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 404) {
            return new Response('Page supprimée', 410);
        }

        // Sinon, renvoyer une réponse avec le code d'erreur existant ou un code 500
        $statusCode = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        return new Response('Erreur inattendue', $statusCode);
    }
}
