<?php

declare(strict_types=1);

namespace App\Application\Sales;

interface CheckoutUrls
{
    public function success(string $orderId, string $locale): string;

    public function cancel(string $orderId, string $locale): string;
}
