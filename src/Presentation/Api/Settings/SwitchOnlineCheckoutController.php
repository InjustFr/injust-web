<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckout;
use App\Application\Settings\SwitchOnlineCheckout\SwitchOnlineCheckoutHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/settings/checkout', name: 'api_settings_checkout', methods: ['PUT'], format: 'json')]
final class SwitchOnlineCheckoutController extends AbstractController
{
    public function __invoke(#[MapRequestPayload] CheckoutPayload $payload, SwitchOnlineCheckoutHandler $switchOnlineCheckout): JsonResponse
    {
        return $this->json($switchOnlineCheckout(new SwitchOnlineCheckout($payload->enabled)));
    }
}
