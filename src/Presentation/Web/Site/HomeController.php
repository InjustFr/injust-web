<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'home', methods: ['GET'])]
final class HomeController extends AbstractController
{
    public function __invoke(): Response
    {
        $response = $this->render('site/home.html.twig');
        $response->setPublic();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }
}
