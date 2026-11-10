<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Exception\EmptyLegalDocument;
use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'legal_document')]
class LegalDocument
{
    #[ORM\Id]
    #[ORM\Column(length: 20, enumType: LegalKind::class)]
    private LegalKind $kind;

    #[ORM\Embedded(class: LocalizedText::class)]
    private LocalizedText $body;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    private function __construct(LegalKind $kind, LocalizedText $body, \DateTimeImmutable $now)
    {
        $this->kind = $kind;
        $this->body = self::nonEmpty($body);
        $this->version = 1;
        $this->updatedAt = $now;
    }

    public static function draft(LegalKind $kind, LocalizedText $body, \DateTimeImmutable $now): self
    {
        return new self($kind, $body, $now);
    }

    public function edit(LocalizedText $body, \DateTimeImmutable $now): void
    {
        $this->body = self::nonEmpty($body);
        ++$this->version;
        $this->updatedAt = $now;
    }

    public function kind(): LegalKind
    {
        return $this->kind;
    }

    public function body(): LocalizedText
    {
        return $this->body;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private static function nonEmpty(LocalizedText $body): LocalizedText
    {
        if ($body->isEmpty()) {
            throw new EmptyLegalDocument();
        }

        return $body;
    }
}
