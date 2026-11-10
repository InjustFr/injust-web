<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\EditLessonOffer\EditLessonOffer;
use App\Application\Lessons\EditLessonOffer\EditLessonOfferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Ulid;

#[Route('/api/admin/lessons/offers/{id}', name: 'api_lessons_offer_edit', requirements: ['id' => Requirement::ULID], methods: ['PUT'], format: 'json')]
final class EditLessonOfferController extends AbstractController
{
    public function __invoke(Ulid $id, #[MapRequestPayload] LessonOfferPayload $payload, EditLessonOfferHandler $editLessonOffer): JsonResponse
    {
        return $this->json($editLessonOffer(new EditLessonOffer($id, $payload->details())));
    }
}
