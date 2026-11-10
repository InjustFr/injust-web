<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Consent;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Order;
use App\Domain\Sales\Terms;
use App\Domain\Shared\Price;

final readonly class OrderView
{
    /** @param list<Consent> $consents */
    private function __construct(
        public string $id,
        public string $reference,
        public string $productType,
        public string $productId,
        public string $productLabel,
        public ?string $productFormat,
        public Price $price,
        public string $locale,
        public string $email,
        public array $consents,
        public int $cgvVersion,
        public Terms $terms,
        public string $status,
        public \DateTimeImmutable $createdAt,
        public ?string $checkoutSessionId,
        public ?string $paymentIntentId,
        public ?\DateTimeImmutable $paidAt,
        public ?Customer $customer,
        public ?string $invoiceNumber,
        public ?\DateTimeImmutable $confirmationSentAt,
    ) {
    }

    public static function of(Order $order): self
    {
        return new self(
            $order->id()->toBase32(),
            $order->reference(),
            $order->productType()->value,
            $order->productId(),
            $order->productLabel(),
            $order->productFormat(),
            $order->price(),
            $order->locale(),
            $order->email(),
            $order->consents(),
            $order->cgvVersion(),
            $order->terms(),
            $order->status()->value,
            $order->createdAt(),
            $order->checkoutSessionId(),
            $order->paymentIntentId(),
            $order->paidAt(),
            $order->customer(),
            $order->invoiceNumber(),
            $order->confirmationSentAt(),
        );
    }
}
