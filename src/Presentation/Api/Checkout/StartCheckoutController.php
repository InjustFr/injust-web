<?php

declare(strict_types=1);

namespace App\Presentation\Api\Checkout;

use App\Application\Sales\StartCheckout\StartCheckout;
use App\Application\Sales\StartCheckout\StartCheckoutHandler;
use App\Presentation\Api\ProblemResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/checkout', name: 'api_checkout_start', methods: ['POST'], format: 'json')]
final class StartCheckoutController extends AbstractController
{
    public function __invoke(Request $request, #[MapRequestPayload] CheckoutPayload $payload, StartCheckoutHandler $startCheckout, RateLimiterFactoryInterface $checkoutLimiter, TranslatorInterface $translator): Response
    {
        if (!$checkoutLimiter->create(IpUtils::anonymize($request->getClientIp() ?? '0.0.0.0', 0, 8))->consume()->isAccepted()) {
            return new ProblemResponse(Response::$statusTexts[Response::HTTP_TOO_MANY_REQUESTS], Response::HTTP_TOO_MANY_REQUESTS, $translator->trans('sales.too_many_checkouts', [], 'exceptions'));
        }
        $started = $startCheckout(new StartCheckout($payload->productType, $payload->productId, $payload->locale, $payload->email, $payload->consents, $payload->cgvVersion));

        return new JsonResponse(['url' => $started->url], Response::HTTP_CREATED);
    }
}
