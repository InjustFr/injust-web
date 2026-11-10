<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Sales\Exception\InvalidRefundAmount;
use App\Domain\Sales\Exception\MissingConsent;
use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\Exception\PaymentMismatch;
use App\Domain\Sales\Exception\RefundBeforePayment;
use App\Domain\Sales\Exception\WithdrawalNotAllowed;
use App\Domain\Sales\Exception\WithdrawalPeriodOver;
use App\Domain\Shared\Exception\UnsupportedLocale;
use App\Domain\Shared\Price;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-import-type ConsentData from Consent
 * @phpstan-import-type CustomerData from Customer
 */
#[ORM\Entity]
#[ORM\Table(name: 'sales_order')]
#[ORM\Index(name: 'sales_order_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'sales_order_paid_at', columns: ['paid_at'])]
#[ORM\Index(name: 'sales_order_payment_intent', columns: ['payment_intent_id'])]
class Order
{
    public const array LOCALES = ['fr', 'en'];
    public const string CURRENCY = 'eur';
    public const string REFERENCE_PREFIX = 'INJ-';
    public const int MAX_LABEL = 255;
    public const int MAX_EMAIL = 180;
    public const int MAX_TYPED_REFERENCE = 20;
    public const int MAX_STRIPE_ID = 255;
    public const string ABANDONED_RETENTION = '-30 days';
    public const int WITHDRAWAL_DAYS = 14;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: 10, unique: true)]
    private string $reference;

    #[ORM\Column(length: 20, enumType: ProductType::class)]
    private ProductType $productType;

    #[ORM\Column(length: 26)]
    private string $productId;

    #[ORM\Column(length: self::MAX_LABEL)]
    private string $productLabel;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $productFormat;

    #[ORM\Embedded(class: Price::class, columnPrefix: 'price_')]
    private Price $price;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(length: self::MAX_EMAIL)]
    private string $email;

    /** @var list<ConsentData> */
    #[ORM\Column(type: 'json')]
    private array $consents;

    #[ORM\Embedded(class: Terms::class, columnPrefix: 'cgv_')]
    private Terms $terms;

    #[ORM\Column(length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: self::MAX_STRIPE_ID, unique: true, nullable: true)]
    private ?string $checkoutSessionId = null;

    #[ORM\Column(length: self::MAX_STRIPE_ID, nullable: true)]
    private ?string $paymentIntentId = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** @var CustomerData|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $customer = null;

    #[ORM\Column(length: 9, nullable: true)]
    private ?string $invoiceNumber = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmationSentAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $refundedCents = 0;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $withdrawnAt = null;

    /** @param list<Consent> $consents */
    private function __construct(Purchasable $product, string $locale, string $email, array $consents, Terms $terms, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->reference = self::REFERENCE_PREFIX.substr($this->id->toBase32(), -6);
        $this->productType = $product->type;
        $this->productId = $product->id;
        $this->productLabel = mb_substr($product->label, 0, self::MAX_LABEL);
        $this->productFormat = $product->format;
        $this->price = $product->price;
        $this->locale = \in_array($locale, self::LOCALES, true) ? $locale : throw new UnsupportedLocale($locale);
        $this->email = trim($email);
        $this->consents = array_map(static fn (Consent $consent): array => $consent->toArray(), self::complete($product->type, $consents));
        $this->terms = $terms;
        $this->createdAt = $now;
    }

    /** @param list<Consent> $consents */
    public static function place(Purchasable $product, string $locale, string $email, array $consents, Terms $terms, \DateTimeImmutable $now): self
    {
        return new self($product, $locale, $email, $consents, $terms, $now);
    }

    public function attachSession(string $sessionId): void
    {
        $this->checkoutSessionId = $sessionId;
    }

    public function pay(PaymentDetails $payment): bool
    {
        if (!\in_array($this->status, [OrderStatus::Pending, OrderStatus::Expired], true)) {
            return false;
        }
        if ($payment->amountCents !== $this->price->cents || self::CURRENCY !== strtolower($payment->currency)) {
            throw new PaymentMismatch($this->reference);
        }
        $this->status = OrderStatus::Paid;
        $this->paymentIntentId = $payment->paymentIntentId;
        $this->paidAt = $payment->paidAt;
        $this->customer = $payment->customer->toArray();
        $this->email = '' === $payment->customer->email ? $this->email : $payment->customer->email;

        return true;
    }

    public function expire(): void
    {
        if (OrderStatus::Pending === $this->status) {
            $this->status = OrderStatus::Expired;
        }
    }

    public function checkRefund(int $cents): void
    {
        if (\in_array($this->status, [OrderStatus::Pending, OrderStatus::Expired], true)) {
            throw new RefundBeforePayment($this->reference);
        }
        $remaining = $this->price->cents - $this->refundedCents;
        if ($cents <= 0 || $cents > $remaining) {
            throw new InvalidRefundAmount($remaining);
        }
    }

    public function refund(int $cents): void
    {
        $this->checkRefund($cents);
        $this->refundedCents += $cents;
        if ($this->refundedCents === $this->price->cents) {
            $this->status = OrderStatus::Refunded;
        }
    }

    public function checkWithdrawal(\DateTimeImmutable $now): void
    {
        $lastDay = $this->withdrawalLastDay();
        if (OrderStatus::Paid !== $this->status || null === $lastDay) {
            throw new WithdrawalNotAllowed($this->reference);
        }
        if ($now >= new \DateTimeImmutable($lastDay->format('Y-m-d'), new \DateTimeZone(FiscalYear::TIMEZONE))->modify('+1 day')) {
            throw new WithdrawalPeriodOver($lastDay);
        }
    }

    public function withdraw(\DateTimeImmutable $now): void
    {
        $this->checkWithdrawal($now);
        $this->status = OrderStatus::Withdrawn;
        $this->withdrawnAt = $now;
    }

    public function withdrawalLastDay(): ?\DateTimeImmutable
    {
        if (null === $this->paidAt || ProductType::Beat === $this->productType) {
            return null;
        }
        $paidOn = $this->paidAt->setTimezone(new \DateTimeZone(FiscalYear::TIMEZONE))->format('Y-m-d');

        return WorkingDay::from(new \DateTimeImmutable($paidOn, new \DateTimeZone('UTC'))->add(new \DateInterval('P'.self::WITHDRAWAL_DAYS.'D')));
    }

    public function belongsTo(string $email): bool
    {
        $email = mb_strtolower(trim($email));

        return '' !== $email && mb_strtolower($this->email) === $email;
    }

    public function invoiced(string $number): void
    {
        $this->invoiceNumber = $number;
    }

    public function confirmationSent(\DateTimeImmutable $now): void
    {
        if (OrderStatus::Paid !== $this->status) {
            throw new OrderNotPaid($this->reference);
        }
        $this->confirmationSentAt = $now;
    }

    public function needsConfirmation(): bool
    {
        return OrderStatus::Paid === $this->status && null === $this->confirmationSentAt;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function productType(): ProductType
    {
        return $this->productType;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function productLabel(): string
    {
        return $this->productLabel;
    }

    public function productFormat(): ?string
    {
        return $this->productFormat;
    }

    public function price(): Price
    {
        return $this->price;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function email(): string
    {
        return $this->email;
    }

    /** @return list<Consent> */
    public function consents(): array
    {
        return array_map(Consent::fromArray(...), $this->consents);
    }

    public function cgvVersion(): int
    {
        return $this->terms->version;
    }

    public function terms(): Terms
    {
        return $this->terms;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function checkoutSessionId(): ?string
    {
        return $this->checkoutSessionId;
    }

    public function paymentIntentId(): ?string
    {
        return $this->paymentIntentId;
    }

    public function paidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function customer(): ?Customer
    {
        return null === $this->customer ? null : Customer::fromArray($this->customer);
    }

    public function invoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function confirmationSentAt(): ?\DateTimeImmutable
    {
        return $this->confirmationSentAt;
    }

    public function refundedCents(): int
    {
        return $this->refundedCents;
    }

    public function withdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    /**
     * @param list<Consent> $consents
     *
     * @return list<Consent>
     */
    private static function complete(ProductType $type, array $consents): array
    {
        $given = array_map(static fn (Consent $consent): ConsentKind => $consent->kind, $consents);
        foreach ($type->requiredConsents() as $required) {
            if (!\in_array($required, $given, true)) {
                throw new MissingConsent();
            }
        }

        return $consents;
    }
}
