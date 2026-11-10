<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\EditLessonsPage\EditLessonsPage;
use App\Application\Lessons\EditLessonsPage\EditLessonsPageHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/lessons/page', name: 'api_lessons_page_edit', methods: ['PUT'], format: 'json')]
final class EditLessonsPageController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] LessonsPagePayload $payload, EditLessonsPageHandler $editLessonsPage): JsonResponse
    {
        return $this->json($editLessonsPage(new EditLessonsPage($payload->intro, $payload->faq)));
    }
}
