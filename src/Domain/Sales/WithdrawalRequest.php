<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'withdrawal_request')]
class WithdrawalRequest
{
    public const int MAX_NAME = 100;
    public const int MAX_REASON = 2000;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $orderId;

    #[ORM\Column(length: 10)]
    private string $orderReference;

    #[ORM\Column(length: Order::MAX_EMAIL)]
    private string $email;

    #[ORM\Column(length: self::MAX_NAME, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    private function __construct(Order $order, string $email, ?string $name, ?string $reason, string $locale, \DateTimeImmutable $now)
    {
        $this->id = new Ulid();
        $this->orderId = $order->id();
        $this->orderReference = $order->reference();
        $this->email = trim($email);
        $this->name = self::optional($name, self::MAX_NAME);
        $this->reason = self::optional($reason, self::MAX_REASON);
        $this->locale = $locale;
        $this->receivedAt = $now;
    }

    public static function receive(Order $order, string $email, ?string $name, ?string $reason, string $locale, \DateTimeImmutable $now): self
    {
        return new self($order, $email, $name, $reason, $locale, $now);
    }

    public function acknowledged(\DateTimeImmutable $now): void
    {
        $this->acknowledgedAt = $now;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function orderId(): Ulid
    {
        return $this->orderId;
    }

    public function orderReference(): string
    {
        return $this->orderReference;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function receivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function acknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    private static function optional(?string $text, int $max): ?string
    {
        $text = mb_substr(trim($text ?? ''), 0, $max);

        return '' === $text ? null : $text;
    }
}
