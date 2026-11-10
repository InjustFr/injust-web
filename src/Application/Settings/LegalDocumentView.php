<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;

final readonly class LegalDocumentView
{
    private function __construct(
        public LegalKind $kind,
        public LocalizedText $body,
        public int $version,
        public \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function of(LegalDocument $document): self
    {
        return new self($document->kind(), $document->body(), $document->version(), $document->updatedAt());
    }
}
