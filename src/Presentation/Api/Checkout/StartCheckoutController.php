<?php

declare(strict_types=1);

namespace App\Presentation\Api\Checkout;

use App\Application\Sales\StartCheckout\StartCheckout;
use App\Application\Sales\StartCheckout\StartCheckoutHandler;
use App\Presentation\Api\IpRateLimit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/checkout', name: 'api_checkout_start', methods: ['POST'], format: 'json')]
final class StartCheckoutController extends AbstractController
{
    public function __invoke(Request $request, #[MapRequestPayload] CheckoutPayload $payload, StartCheckoutHandler $startCheckout, IpRateLimit $limit, RateLimiterFactoryInterface $checkoutLimiter): Response
    {
        $refused = $limit->refuse($checkoutLimiter, $request, 'sales.too_many_checkouts');
        if (null !== $refused) {
            return $refused;
        }
        $started = $startCheckout(new StartCheckout($payload->productType, $payload->productId, $payload->locale, $payload->email, $payload->consents, $payload->cgvVersion));

        return new JsonResponse(['url' => $started->url], Response::HTTP_CREATED);
    }
}
