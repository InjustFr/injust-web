<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Sales\Consent;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\Customer;
use App\Domain\Sales\LicenceSnapshot;
use App\Domain\Sales\Order;
use App\Domain\Sales\Terms;
use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Shared\Price;

final readonly class OrderView
{
    /**
     * @param list<Consent>        $consents
     * @param list<CreditNoteView> $creditNotes
     */
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
        public int $refundedCents,
        public ?\DateTimeImmutable $withdrawnAt,
        public ?\DateTimeImmutable $withdrawalLastDay,
        public ?WithdrawalRequestView $withdrawal,
        public array $creditNotes,
        public ?LicenceSnapshot $licence,
        public ?\DateTimeImmutable $exclusiveConflictAt,
    ) {
    }

    /** @param list<CreditNote> $creditNotes */
    public static function of(Order $order, ?WithdrawalRequest $withdrawal = null, array $creditNotes = []): self
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
            $order->refundedCents(),
            $order->withdrawnAt(),
            $order->withdrawalLastDay(),
            null === $withdrawal ? null : WithdrawalRequestView::of($withdrawal),
            array_map(CreditNoteView::of(...), $creditNotes),
            $order->licence(),
            $order->exclusiveConflictAt(),
        );
    }
}
