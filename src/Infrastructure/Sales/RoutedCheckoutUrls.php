<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\CheckoutUrls;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class RoutedCheckoutUrls implements CheckoutUrls
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public function success(string $orderId, string $locale): string
    {
        return $this->generate('checkout_success', $orderId, $locale);
    }

    public function cancel(string $orderId, string $locale): string
    {
        return $this->generate('checkout_cancel', $orderId, $locale);
    }

    private function generate(string $route, string $orderId, string $locale): string
    {
        return $this->urls->generate($route, ['_locale' => $locale, 'order' => $orderId], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
