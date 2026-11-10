<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Sales\CheckoutUrls;

final class FakeCheckoutUrls implements CheckoutUrls
{
    public function success(string $orderId, string $locale): string
    {
        return 'https://injust.test/'.$locale.'/checkout/success?order='.$orderId;
    }

    public function cancel(string $orderId, string $locale): string
    {
        return 'https://injust.test/'.$locale.'/checkout/cancel?order='.$orderId;
    }
}
