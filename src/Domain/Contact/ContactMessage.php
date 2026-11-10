<?php

declare(strict_types=1);

namespace App\Domain\Contact;

use App\Domain\Contact\Exception\UnsupportedLocale;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'contact_message')]
#[ORM\Index(name: 'contact_message_received_at', columns: ['received_at'])]
class ContactMessage
{
    public const int MAX_NAME = 100;
    public const int MAX_EMAIL = 180;
    public const int MAX_OFFER_REF = 60;
    public const int MAX_OFFER_LABEL = 200;
    public const int MIN_BODY = 10;
    public const int MAX_BODY = 5000;
    public const array LOCALES = ['en', 'fr'];
    public const string RETENTION = '-3 years';

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    private Ulid $id;

    #[ORM\Column(length: self::MAX_NAME)]
    private string $name;

    #[ORM\Column(length: self::MAX_EMAIL)]
    private string $email;

    #[ORM\Column(length: 20, enumType: ContactTopic::class)]
    private ContactTopic $topic;

    #[ORM\Column(length: self::MAX_OFFER_REF, nullable: true)]
    private ?string $offerRef;

    #[ORM\Column(length: self::MAX_OFFER_LABEL, nullable: true)]
    private ?string $offerLabel;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(length: 2)]
    private string $locale;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    private function __construct(string $name, string $email, ContactTopic $topic, string $body, string $locale, \DateTimeImmutable $receivedAt, ?string $offerRef, ?string $offerLabel)
    {
        $this->id = new Ulid();
        $this->name = trim($name);
        $this->email = trim($email);
        $this->topic = $topic;
        $this->body = trim($body);
        $this->locale = \in_array($locale, self::LOCALES, true) ? $locale : throw new UnsupportedLocale($locale);
        $this->receivedAt = $receivedAt;
        $this->offerRef = self::optional($offerRef);
        $this->offerLabel = self::optional(mb_substr($offerLabel ?? '', 0, self::MAX_OFFER_LABEL));
    }

    public static function receive(string $name, string $email, ContactTopic $topic, string $body, string $locale, \DateTimeImmutable $receivedAt, ?string $offerRef = null, ?string $offerLabel = null): self
    {
        return new self($name, $email, $topic, $body, $locale, $receivedAt, $offerRef, $offerLabel);
    }

    public function markHandled(\DateTimeImmutable $at): void
    {
        $this->handledAt ??= $at;
    }

    public function markUnhandled(): void
    {
        $this->handledAt = null;
    }

    public function id(): Ulid
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function topic(): ContactTopic
    {
        return $this->topic;
    }

    public function offerRef(): ?string
    {
        return $this->offerRef;
    }

    public function offerLabel(): ?string
    {
        return $this->offerLabel;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function receivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function handledAt(): ?\DateTimeImmutable
    {
        return $this->handledAt;
    }

    private static function optional(?string $value): ?string
    {
        $value = trim($value ?? '');

        return '' === $value ? null : $value;
    }
}
