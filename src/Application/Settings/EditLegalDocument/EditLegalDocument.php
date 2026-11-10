<?php

declare(strict_types=1);

namespace App\Application\Settings\EditLegalDocument;

use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;

final readonly class EditLegalDocument
{
    public function __construct(
        public LegalKind $kind,
        public LocalizedText $body,
    ) {
    }
}
