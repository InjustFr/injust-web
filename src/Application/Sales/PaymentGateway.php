<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\PaymentDetails;

interface PaymentGateway
{
    public function startCheckout(CheckoutRequest $request): CheckoutSession;

    public function paymentDetails(string $sessionId): PaymentDetails;
}
