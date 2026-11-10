<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Application\Links\LinksQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/links', name: 'api_links_list', methods: ['GET'], format: 'json')]
final class ListBioLinksController extends AbstractController
{
    public function __invoke(LinksQueries $queries): JsonResponse
    {
        return $this->json($queries->links());
    }
}
