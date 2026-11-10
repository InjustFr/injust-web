<?php

declare(strict_types=1);

namespace App\Presentation\Api\Beats;

use App\Application\Beats\BeatsQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/beats', name: 'api_beats_show', methods: ['GET'], format: 'json')]
final class ShowBeatsController extends AbstractController
{
    public function __invoke(BeatsQueries $queries): JsonResponse
    {
        return $this->json($queries->admin());
    }
}
