<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\Section;
use App\Domain\Settings\Settings;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;

final readonly class SettingsView
{
    /** @param array<value-of<Section>, bool> $sections */
    private function __construct(
        public array $sections,
        public bool $onlineCheckout,
        public string $contactEmail,
        public ?string $bookingUrl,
        public LegalIdentityView $legal,
        public VatMentions $vatMentions,
        public LocalizedText $seoTitle,
        public LocalizedText $seoDescription,
        public ?string $logoImage,
        public ?string $heroImage,
        public bool $searchIndexing,
    ) {
    }

    public static function of(Settings $settings): self
    {
        $sections = [];
        foreach (Section::cases() as $section) {
            $sections[$section->value] = $settings->isEnabled($section);
        }

        return new self(
            $sections,
            $settings->onlineCheckout(),
            $settings->contactEmail(),
            $settings->bookingUrl(),
            LegalIdentityView::of($settings->legalIdentity()),
            $settings->vatMentions(),
            $settings->seoTitle(),
            $settings->seoDescription(),
            $settings->logoImage(),
            $settings->heroImage(),
            $settings->searchIndexing(),
        );
    }

    public function isEnabled(Section $section): bool
    {
        return $this->sections[$section->value];
    }

    /** @return list<Section> */
    public function enabledSections(): array
    {
        return array_values(array_filter(Section::cases(), $this->isEnabled(...)));
    }
}
