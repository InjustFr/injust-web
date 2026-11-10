<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\OrderView;

final class OrderJson
{
    /** @return array<string, mixed> */
    public static function of(OrderView $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'productType' => $order->productType,
            'productId' => $order->productId,
            'productLabel' => $order->productLabel,
            'productFormat' => $order->productFormat,
            'amountCents' => $order->price->cents,
            'locale' => $order->locale,
            'email' => $order->email,
            'consents' => array_map(static fn ($consent): array => ['kind' => $consent->kind->value, 'text' => $consent->text, 'acceptedAt' => $consent->acceptedAt->format(\DATE_ATOM)], $order->consents),
            'cgvVersion' => $order->cgvVersion,
            'status' => $order->status,
            'createdAt' => $order->createdAt->format(\DATE_ATOM),
            'paidAt' => $order->paidAt?->format(\DATE_ATOM),
            'customer' => null === $order->customer ? null : get_object_vars($order->customer),
            'invoiceNumber' => $order->invoiceNumber,
            'confirmationSentAt' => $order->confirmationSentAt?->format(\DATE_ATOM),
        ];
    }
}
