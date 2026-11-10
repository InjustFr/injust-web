<?php

declare(strict_types=1);

namespace App\Presentation\Web\FakeCheckout;

use App\Presentation\Web\Webhooks\StripeSignature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/_fake-checkout/{session}/pay', name: 'fake_checkout_pay', requirements: ['session' => 'cs_[A-Za-z0-9_]+'], methods: ['POST'], env: ['dev', 'test'])]
final class PayFakeCheckoutController extends AbstractController
{
    public function __invoke(string $session, HttpClientInterface $httpClient, StripeSignature $signature, HttpKernelInterface $kernel): Response
    {
        $paid = $httpClient->request('POST', 'https://api.stripe.com/v1/test_helpers/checkout/sessions/'.$session.'/pay');
        if (Response::HTTP_OK !== $paid->getStatusCode()) {
            throw $this->createNotFoundException();
        }
        $checkout = $paid->toArray();
        $event = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $checkout]], \JSON_THROW_ON_ERROR);
        $answer = $kernel->handle(Request::create('/webhooks/stripe', 'POST', server: ['HTTP_STRIPE_SIGNATURE' => $signature->sign($event)], content: $event), HttpKernelInterface::SUB_REQUEST);

        return $answer->isSuccessful() && \is_string($checkout['success_url'] ?? null) ? new RedirectResponse($checkout['success_url']) : $answer;
    }
}
