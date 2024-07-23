<?php

namespace App\Controller\Api\Insert;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Doctrine\ORM\EntityManagerInterface;

class UsersWordpressController extends AbstractController
{
    private $httpClient;
    private $entityManager;

    public function __construct(HttpClientInterface $httpClient, EntityManagerInterface $entityManager)
    {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
    }

    #[Route('/api/insert/users/wordpress', name: 'api_insert_users_wordpress')]
    public function index(): JsonResponse
    {
        try {
            $token = $this->getJwtToken();
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }

        $url = 'https://justfocus.fr/wp-json/wp/v2/users';
        $users = [];
        $page = 1;

        do {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'query' => [
                    'page' => $page,
                    'per_page' => 5
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode !== 200) {
                return $this->json([
                    'error' => 'Une erreur est survenue',
                    'status_code' => $statusCode,
                    'response' => $content
                ], 500);
            }

            $currentPageUsers = json_decode($content, true);
            $users = array_merge($users, $currentPageUsers);
            $page++;
        } while (count($currentPageUsers) > 0);

        foreach ($users as $userData) {
            if (!isset($userData['email']) || empty($userData['email'])) {
                $userData['email'] = 'user_' . uniqid() . '@example.com'; // Generate a unique email
            }
        
            $userEntity = new User();
            $userEntity->setId($userData['id'] ?? 0);
            $userEntity->setPseudo($userData['name'] ?? '');
            $userEntity->setFirstName($userData['name'] ?? '');
            $userEntity->setLastName($userData['name'] ?? '');
            $userEntity->setEmail($userData['email'] ?? 'toto@toto.fr');
            $userEntity->setRoles(['ROLE_USER']);
            $userEntity->setPassword('123456');

            $this->entityManager->persist($userEntity);
           
        }

        $this->entityManager->flush();

        return $this->json(['message' => 'Les utilisateurs ont bien été insérés'], 200);
    }

    private function getJwtToken(): string
    {
        $url = 'https://justfocus.fr/wp-json/jwt-auth/v1/token';
        $response = $this->httpClient->request('POST', $url, [
            'json' => [
                'username' => 'guillaume2vo',
                'password' => 'uKOdDIGLj9&LlcTzpdkb!#*K',
                'email' => 'SUPPORT@AUXIOMA.EU'
            ]
        ]);

        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Could not retrieve JWT token: ' . ($data['message'] ?? 'Unknown error'));
        }

        if (!isset($data['token'])) {
            throw new \Exception('JWT token is missing from the response. Full response: ' . json_encode($data));
        }

        $token = trim($data['token']);

        // Vérifiez la structure du jeton JWT
        if (substr_count($token, '.') !== 2) {
            throw new \Exception('JWT token is not correctly formed. Token: ' . $token . ' Full response: ' . json_encode($data));
        }

        return $token;
    }
}
