<?php

declare(strict_types=1);

namespace App\Presentation\Api\Lessons;

use App\Application\Lessons\OrderLessonOffers\OrderLessonOffers;
use App\Application\Lessons\OrderLessonOffers\OrderLessonOffersHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/lessons/offers/order', name: 'api_lessons_offer_order', methods: ['PUT'], format: 'json')]
final class OrderLessonOffersController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderLessonOffersHandler $orderLessonOffers): JsonResponse
    {
        return $this->json($orderLessonOffers(new OrderLessonOffers($payload->ids)));
    }
}
