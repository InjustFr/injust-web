<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutSession;
use App\Application\Sales\PaymentGateway;
use App\Application\Sales\StripeRefund;
use App\Domain\Sales\PaymentDetails;

final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<CheckoutRequest> */
    public array $requests = [];

    /** @var array<string, PaymentDetails> */
    public array $payments = [];

    public bool $down = false;

    /** @var list<array{string, int, string, ?string}> */
    public array $refundRequests = [];

    /** @var array<string, list<StripeRefund>> */
    public array $refunds = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function startCheckout(CheckoutRequest $request): CheckoutSession
    {
        if ($this->down) {
            throw new \RuntimeException('Stripe is down.');
        }
        $this->requests[] = $request;
        $id = 'cs_'.\count($this->requests);

        return new CheckoutSession($id, 'https://pay.test/'.$id);
    }

    public function paymentDetails(string $sessionId): PaymentDetails
    {
        $this->journal->write('details '.$sessionId);

        return $this->payments[$sessionId] ?? throw new \RuntimeException('Unknown session '.$sessionId);
    }

    public function refund(string $paymentIntentId, int $amountCents, string $idempotencyKey, ?string $reason): StripeRefund
    {
        if ($this->down) {
            throw new \RuntimeException('Stripe is down.');
        }
        $this->refundRequests[] = [$paymentIntentId, $amountCents, $idempotencyKey, $reason];

        return new StripeRefund('re_'.\count($this->refundRequests), $amountCents, 'succeeded', $reason);
    }

    public function refunds(string $paymentIntentId): array
    {
        $this->journal->write('refunds '.$paymentIntentId);

        return $this->refunds[$paymentIntentId] ?? [];
    }
}
