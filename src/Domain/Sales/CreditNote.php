<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Price;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-import-type SellerData from Seller
 * @phpstan-import-type CustomerData from Customer
 * @phpstan-import-type InvoiceLineData from InvoiceLine
 */
#[ORM\Entity]
#[ORM\Table(name: 'credit_note')]
#[ORM\Index(name: 'credit_note_order', columns: ['order_id'])]
#[ORM\Index(name: 'credit_note_issued_at', columns: ['issued_at'])]
class CreditNote
{
    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 9, unique: true)]
    private string $number;

    #[ORM\Column(length: 9)]
    private string $invoiceNumber;

    #[ORM\Column(type: UlidType::NAME)]
    private Ulid $orderId;

    #[ORM\Column(length: 10)]
    private string $orderReference;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $issuedAt;

    /** @var SellerData */
    #[ORM\Column(type: 'json')]
    private array $seller;

    /** @var CustomerData */
    #[ORM\Column(type: 'json')]
    private array $buyer;

    /** @var list<InvoiceLineData> */
    #[ORM\Column(type: 'json')]
    private array $lines;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'amount_')]
    private Price $amount;

    #[ORM\Column(length: VatMentions::MAX_TEXT)]
    private string $vatMention;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(length: Order::MAX_STRIPE_ID, unique: true)]
    private string $stripeRefundId;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $mailedAt = null;

    private function __construct(string $number, Invoice $invoice, Price $amount, string $stripeRefundId, ?string $reason, \DateTimeImmutable $issuedAt)
    {
        $this->id = new Ulid();
        $this->number = $number;
        $this->invoiceNumber = $invoice->number();
        $this->orderId = $invoice->orderId();
        $this->orderReference = $invoice->orderReference();
        $this->locale = $invoice->locale();
        $this->issuedAt = $issuedAt;
        $this->seller = $invoice->seller()->toArray();
        $this->buyer = $invoice->buyer()->toArray();
        $this->lines = [new InvoiceLine('Avoir sur facture '.$invoice->number().' — '.$invoice->lines()[0]->label, 1, $amount, $amount)->toArray()];
        $this->amount = $amount;
        $this->vatMention = $invoice->vatMention();
        $reason = trim($reason ?? '');
        $this->reason = '' === $reason ? null : $reason;
        $this->stripeRefundId = $stripeRefundId;
    }

    public static function issue(string $number, Invoice $invoice, int $amountCents, string $stripeRefundId, ?string $reason, \DateTimeImmutable $issuedAt): self
    {
        return new self($number, $invoice, new Price($amountCents), $stripeRefundId, $reason, $issuedAt);
    }

    public function mailed(\DateTimeImmutable $now): void
    {
        $this->mailedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function number(): string
    {
        return $this->number;
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function orderId(): Ulid
    {
        return $this->orderId;
    }

    public function orderReference(): string
    {
        return $this->orderReference;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function seller(): Seller
    {
        return Seller::fromArray($this->seller);
    }

    public function buyer(): Customer
    {
        return Customer::fromArray($this->buyer);
    }

    /** @return list<InvoiceLine> */
    public function lines(): array
    {
        return array_map(InvoiceLine::fromArray(...), $this->lines);
    }

    public function amount(): Price
    {
        return $this->amount;
    }

    public function vatMention(): string
    {
        return $this->vatMention;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function stripeRefundId(): string
    {
        return $this->stripeRefundId;
    }

    public function mailedAt(): ?\DateTimeImmutable
    {
        return $this->mailedAt;
    }
}
