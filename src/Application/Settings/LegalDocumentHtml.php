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
        return $this->markdown->toHtml($document->body->in($locale), self::placeholders($document, $settings, $locale, $siteUrl));
    }

    public function fill(LegalDocumentView $document, SettingsView $settings, string $locale, string $siteUrl): string
    {
        return LegalMarkdown::fill('fr' === $locale ? $document->body->fr : $document->body->en, self::placeholders($document, $settings, $locale, $siteUrl));
    }

    /** @return array<string, string> */
    private static function placeholders(LegalDocumentView $document, SettingsView $settings, string $locale, string $siteUrl): array
    {
        $legal = $settings->legal;
        $date = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, $document->updatedAt->getTimezone());

        return [
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
            'vatNumber' => $legal->vatNumber,
            'vatMention' => $settings->vatMentions->consumer,
            'siteUrl' => $siteUrl,
            'updatedAt' => (string) $date->format($document->updatedAt),
        ];
    }
}
