<?php

declare(strict_types=1);

namespace App\Presentation\Api\Settings;

use App\Domain\Shared\LocalizedText;

final readonly class LegalDocumentPayload
{
    public function __construct(public LocalizedText $body = new LocalizedText())
    {
    }
}
