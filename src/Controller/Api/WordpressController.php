<?php

namespace App\Controller\Api;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class WordpressController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ){}

    #[Route('/api/wordpress/category', name: 'app_api_category')]
    public function index(): Response
    {
        // URL de l'API WordPress pour récupérer les catégories
        $url = 'https://justfocus.fr/wp-json/wp/v2/categories?per_page=100&lang=fr';

        // Initialiser cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Exécuter la requête et obtenir la réponse
        $response = curl_exec($ch);

        // Fermer cURL
        curl_close($ch);

        // Vérifier si la réponse est valide
        if ($response === false) {
            return new Response('Erreur lors de la récupération des catégories.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Décoder les données JSON en tableau PHP
        $categories = json_decode($response, true);

        // Vérifier si le décodage JSON a réussi
        if ($categories === null) {
            return new Response('Erreur lors du décodage des catégories JSON.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        echo '<pre>';
        print_r($categories);
        echo '</pre>';

        // Parcourir les catégories et les enregistrer en base de données
        foreach ($categories as $categoryData) {
            // je vais filtré les catégories qui ont un parent = 0
            if ($categoryData['parent'] === 0) {
                // Créer une nouvelle instance de l'entité Category
                echo $categoryData['id'] . '<br>';
                $category = new Category();
                $category->setId($categoryData['id']);
                $category->setCount($categoryData['count']);
                $category->setDescription($categoryData['description']);
                $category->setName($categoryData['name']);
                $category->setSlug($categoryData['slug']);

                // Enregistrer la catégorie en base de données
                $this->entityManager->persist($category);
            }
        }
        // Exécuter les requêtes SQL pour enregistrer les catégories
        $this->entityManager->flush();

        // Parcourir les catégories pour créer les relations parent-enfant
        foreach ($categories as $SousCategory) {
            $id = $SousCategory['parent'];
            $category = $this->entityManager->getRepository(Category::class)->findOneBy(['id' => $id]);
            // je vais filtré les catégories qui ont un parent != 0
            if ($SousCategory['parent'] != 0) {
                $subcategory = new Category();
                $subcategory->setId($SousCategory['id']);
                $subcategory->setCount($SousCategory['count']);
                $subcategory->setDescription($SousCategory['description']);
                $subcategory->setName($SousCategory['name']);
                $subcategory->setSlug($SousCategory['slug']);
                $subcategory->setParent($category);

                // Enregistrer la sous-catégorie en base de données
                $this->entityManager->persist($subcategory);
            }
            $this->entityManager->flush();
        }

        return $this->render('api/wordpress/index.html.twig');
    }
}
