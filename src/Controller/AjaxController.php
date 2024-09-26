<?php

namespace App\Controller;

use App\Entity\Articles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends AbstractController
{
    #[Route('/article/{id}/post/like', name: 'post_like', methods: ['POST'])]
    public function index(Articles $articles, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $articles->incrementLikes();
        $entityManager->flush();

        return new JsonResponse(['likes' => $articles->getLikes()]);
    }

}