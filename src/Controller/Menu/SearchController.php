<?php

namespace App\Controller\Menu;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SearchController extends AbstractController
{
    public function search()
    {
        return $this->render('_partials/search.html.twig');
    }
}