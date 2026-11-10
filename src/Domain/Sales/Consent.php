<?php

declare(strict_types=1);

namespace App\Domain\Sales;

/**
 * @phpstan-type ConsentData array{kind: string, text: string, acceptedAt: string}
 */
final readonly class Consent
{
    public function __construct(
        public ConsentKind $kind,
        public string $text,
        public \DateTimeImmutable $acceptedAt,
    ) {
    }

    /** @param ConsentData $data */
    public static function fromArray(array $data): self
    {
        return new self(ConsentKind::from($data['kind']), $data['text'], new \DateTimeImmutable($data['acceptedAt']));
    }

    /** @return ConsentData */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'text' => $this->text, 'acceptedAt' => $this->acceptedAt->format(\DATE_ATOM)];
    }
}
