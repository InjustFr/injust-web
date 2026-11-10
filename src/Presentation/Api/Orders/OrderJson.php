<?php

declare(strict_types=1);

namespace App\Presentation\Api\Orders;

use App\Application\Sales\DownloadLink;
use App\Application\Sales\OrderView;

final class OrderJson
{
    /** @return array<string, mixed> */
    public static function of(OrderView $order): array
    {
        $licence = $order->licence?->beat;

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
            'refundedCents' => $order->refundedCents,
            'withdrawal' => null === $order->withdrawal ? null : [
                'receivedAt' => $order->withdrawal->receivedAt->format(\DATE_ATOM),
                'name' => $order->withdrawal->name,
                'reason' => $order->withdrawal->reason,
                'acknowledgedAt' => $order->withdrawal->acknowledgedAt?->format(\DATE_ATOM),
            ],
            'creditNotes' => array_map(static fn ($note): array => [
                'id' => $note->id,
                'number' => $note->number,
                'amountCents' => $note->amount->cents,
                'issuedAt' => $note->issuedAt->format(\DATE_ATOM),
                'reason' => $note->reason,
            ], $order->creditNotes),
            'licence' => null === $licence ? null : [
                'beatTitle' => $licence->title,
                'tierName' => $licence->tierName->in($order->locale),
                'exclusive' => $licence->exclusive,
                'deliverables' => $licence->deliverables,
            ],
            'exclusiveConflictAt' => $order->exclusiveConflictAt?->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<DownloadLink> $downloads
     *
     * @return array<string, mixed>
     */
    public static function detail(OrderView $order, array $downloads): array
    {
        return [...self::of($order), 'downloads' => $downloads];
    }
}
