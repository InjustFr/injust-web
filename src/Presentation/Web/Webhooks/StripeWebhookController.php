<?php

declare(strict_types=1);

namespace App\Presentation\Web\Webhooks;

use App\Application\Sales\ConfirmPayment\ConfirmPayment;
use App\Application\Sales\ConfirmPayment\ConfirmPaymentHandler;
use App\Application\Sales\ExpireCheckout\ExpireCheckout;
use App\Application\Sales\ExpireCheckout\ExpireCheckoutHandler;
use App\Application\Sales\RefundPayment\RefundPayment;
use App\Application\Sales\RefundPayment\RefundPaymentHandler;
use App\Domain\Sales\Exception\OrderNotFound;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
final readonly class StripeWebhookController
{
    private const array PAID = ['checkout.session.completed', 'checkout.session.async_payment_succeeded'];
    private const string REFUNDED = 'charge.refunded';

    public function __construct(
        private StripeSignature $signature,
        private ConfirmPaymentHandler $confirmPayment,
        private ExpireCheckoutHandler $expireCheckout,
        private RefundPaymentHandler $refundPayment,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $event = $this->signature->verify($payload, $request->headers->get('Stripe-Signature')) ? StripeEvent::parse($payload) : null;
        if (null === $event) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->handle($event);
        } catch (OrderNotFound) {
            return new Response('', self::REFUNDED === $event->type ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK);
        }

        return new Response();
    }

    private function handle(StripeEvent $event): void
    {
        if (\in_array($event->type, self::PAID, true) && 'paid' === $event->text('payment_status')) {
            ($this->confirmPayment)(new ConfirmPayment($event->text('id')));
        } elseif ('checkout.session.expired' === $event->type) {
            ($this->expireCheckout)(new ExpireCheckout($event->text('id')));
        } elseif (self::REFUNDED === $event->type && $event->flag('refunded')) {
            ($this->refundPayment)(new RefundPayment($event->text('payment_intent')));
        }
    }
}
