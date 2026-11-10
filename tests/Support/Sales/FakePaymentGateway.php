<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutSession;
use App\Application\Sales\PaymentGateway;
use App\Domain\Sales\PaymentDetails;

final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<CheckoutRequest> */
    public array $requests = [];

    /** @var array<string, PaymentDetails> */
    public array $payments = [];

    public bool $down = false;

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
}
