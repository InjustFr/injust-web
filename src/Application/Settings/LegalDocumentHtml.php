<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Application\Shared\LegalMarkdown;

final readonly class LegalDocumentHtml
{
    public function __construct(private LegalMarkdown $markdown)
    {
    }

    public function render(LegalDocumentView $document, SettingsView $settings, string $locale, string $siteUrl): string
    {
        $legal = $settings->legal;
        $date = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, $document->updatedAt->getTimezone());

        return $this->markdown->toHtml($document->body->in($locale), [
            'name' => $legal->name,
            'businessForm' => $legal->businessForm,
            'siren' => $legal->siren,
            'address' => $legal->address,
            'email' => $legal->email,
            'phone' => $legal->phone,
            'publicationDirector' => $legal->publicationDirector,
            'mediatorName' => $legal->mediatorName,
            'mediatorUrl' => $legal->mediatorUrl,
            'hostName' => $legal->hostName,
            'hostAddress' => $legal->hostAddress,
            'hostPhone' => $legal->hostPhone,
            'vatMention' => $settings->vatMentions->consumer,
            'siteUrl' => $siteUrl,
            'updatedAt' => (string) $date->format($document->updatedAt),
        ]);
    }
}
