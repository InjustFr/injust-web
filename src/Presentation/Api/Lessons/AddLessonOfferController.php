<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\AddLessonOffer\AddLessonOffer;
use App\Application\Lessons\AddLessonOffer\AddLessonOfferHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/lessons/offers', name: 'api_lessons_offer_add', methods: ['POST'], format: 'json')]
final class AddLessonOfferController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] LessonOfferPayload $payload, AddLessonOfferHandler $addLessonOffer): JsonResponse
    {
        return $this->json($addLessonOffer(new AddLessonOffer($payload->details())), JsonResponse::HTTP_CREATED);
    }
}
