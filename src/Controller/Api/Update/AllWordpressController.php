<?php

namespace App\Controller\Api\Update;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class AllWordpressController extends AbstractController
{
    /**
     * I'm update the database when the date of the post is the same as the day
     */

     const WORDPRESS_API_URL = 'https://justfocus.fr/wp-json/wp/v2/posts?per_page=10&lang=fr';

    public function index(): Response
    {

    }
    
}