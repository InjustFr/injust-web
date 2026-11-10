<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Sales\Exception\OrderNotPaid;
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
#[ORM\Table(name: 'invoice')]
#[ORM\Index(name: 'invoice_order', columns: ['order_id'])]
class Invoice
{
    public const string PAYMENT_METHOD = 'card';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 9, unique: true)]
    private string $number;

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

    #[ORM\Embedded(class: Price::class, columnPrefix: 'total_')]
    private Price $total;

    #[ORM\Column(length: 20, enumType: VatCase::class)]
    private VatCase $vatCase;

    #[ORM\Column(length: VatMentions::MAX_TEXT)]
    private string $vatMention;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $paidAt;

    #[ORM\Column(length: 20)]
    private string $paymentMethod = self::PAYMENT_METHOD;

    #[ORM\Column(length: 9, nullable: true)]
    private ?string $creditedInvoiceNumber = null;

    private function __construct(string $number, Order $order, Customer $buyer, \DateTimeImmutable $paidAt, Seller $seller, VatMentions $mentions, \DateTimeImmutable $issuedAt)
    {
        $this->id = new Ulid();
        $this->number = $number;
        $this->orderId = $order->id();
        $this->orderReference = $order->reference();
        $this->locale = $order->locale();
        $this->issuedAt = $issuedAt;
        $this->seller = $seller->toArray();
        $this->buyer = $buyer->toArray();
        $this->lines = [new InvoiceLine($order->productLabel(), 1, $order->price(), $order->price())->toArray()];
        $this->total = $order->price();
        $this->vatCase = VatCase::of($buyer);
        $this->vatMention = $this->vatCase->mention($mentions);
        $this->paidAt = $paidAt;
    }

    public static function issue(string $number, Order $order, Seller $seller, VatMentions $mentions, \DateTimeImmutable $issuedAt): self
    {
        if (OrderStatus::Paid !== $order->status()) {
            throw new OrderNotPaid($order->reference());
        }
        $buyer = $order->customer();
        $paidAt = $order->paidAt();
        \assert(null !== $buyer);
        \assert(null !== $paidAt);

        return new self($number, $order, $buyer, $paidAt, $seller, $mentions, $issuedAt);
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function number(): string
    {
        return $this->number;
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

    public function total(): Price
    {
        return $this->total;
    }

    public function vatCase(): VatCase
    {
        return $this->vatCase;
    }

    public function vatMention(): string
    {
        return $this->vatMention;
    }

    public function paidAt(): \DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function paymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function creditedInvoiceNumber(): ?string
    {
        return $this->creditedInvoiceNumber;
    }
}
