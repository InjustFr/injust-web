<?php

declare(strict_types=1);

namespace App\Presentation\Api\Music;

use App\Application\Music\OrderSocialLinks\OrderSocialLinks;
use App\Application\Music\OrderSocialLinks\OrderSocialLinksHandler;
use App\Presentation\Api\Shared\OrderPayload;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/music/links/order', name: 'api_music_link_order', methods: ['PUT'], format: 'json')]
final class OrderSocialLinksController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] OrderPayload $payload, OrderSocialLinksHandler $orderSocialLinks): JsonResponse
    {
        return $this->json($orderSocialLinks(new OrderSocialLinks($payload->ids)));
    }
}
