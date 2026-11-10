<?php

declare(strict_types=1);

namespace App\Infrastructure\Sales;

use App\Application\Sales\ConsentTexts;
use App\Domain\Sales\ConsentKind;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class TranslatedConsentTexts implements ConsentTexts
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function text(ConsentKind $kind, string $locale, int $version): string
    {
        return $this->translator->trans('sales.consents.'.$kind->value, ['version' => $version], 'messages', $locale);
    }
}
