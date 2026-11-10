<?php

declare(strict_types=1);

namespace App\Application\Contact;

use App\Domain\Contact\ContactMessage;

final readonly class ContactMessageView
{
    private function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $topic,
        public ?string $offerRef,
        public ?string $offerLabel,
        public string $body,
        public string $locale,
        public \DateTimeImmutable $receivedAt,
        public bool $handled,
        public ?\DateTimeImmutable $handledAt,
    ) {
    }

    public static function of(ContactMessage $message): self
    {
        return new self(
            $message->id()->toBase32(),
            $message->name(),
            $message->email(),
            $message->topic()->value,
            $message->offerRef(),
            $message->offerLabel(),
            $message->body(),
            $message->locale(),
            $message->receivedAt(),
            null !== $message->handledAt(),
            $message->handledAt(),
        );
    }
}
