<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\LessonsQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/lessons', name: 'api_lessons_show', methods: ['GET'], format: 'json')]
final class ShowLessonsController extends AbstractController
{
    public function __invoke(LessonsQueries $queries): JsonResponse
    {
        return $this->json(['page' => $queries->text(), 'offers' => $queries->offers()]);
    }
}
