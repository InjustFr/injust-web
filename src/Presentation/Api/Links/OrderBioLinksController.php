<?php

declare(strict_types=1);

namespace App\Presentation\Api\Links;

use App\Application\Links\OrderBioLinks\OrderBioLinks;
use App\Application\Links\OrderBioLinks\OrderBioLinksHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/links/order', name: 'api_links_order', methods: ['PUT'], format: 'json')]
final class OrderBioLinksController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderBioLinksHandler $orderBioLinks): JsonResponse
    {
        return $this->json($orderBioLinks(new OrderBioLinks($payload->ids)));
    }
}
