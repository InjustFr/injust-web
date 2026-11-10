<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Exception\InvalidVerificationToken;
use App\Domain\Settings\Exception\LegalIdentityIncomplete;
use App\Domain\Shared\HttpsUrl;
use App\Domain\Shared\LocalizedText;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'settings')]
class Settings
{
    public const int ID = 1;
    public const int MAX_TEXT = 255;
    public const int MAX_VERIFICATION_TOKEN = 100;

    #[ORM\Id]
    #[ORM\Column]
    private int $id = self::ID;

    #[ORM\Column]
    private bool $lessonsEnabled = false;

    #[ORM\Column]
    private bool $productionEnabled = false;

    #[ORM\Column]
    private bool $beatsEnabled = false;

    #[ORM\Column]
    private bool $onlineCheckout = false;

    #[ORM\Column(length: self::MAX_TEXT)]
    private string $contactEmail = '';

    #[ORM\Column(length: self::MAX_TEXT, nullable: true)]
    private ?string $bookingUrl = null;

    #[ORM\Embedded(class: LegalIdentity::class, columnPrefix: 'legal_')]
    private LegalIdentity $legalIdentity;

    #[ORM\Embedded(class: VatMentions::class, columnPrefix: 'vat_')]
    private VatMentions $vatMentions;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'seo_title_')]
    private LocalizedText $seoTitle;

    #[ORM\Embedded(class: LocalizedText::class, columnPrefix: 'seo_description_')]
    private LocalizedText $seoDescription;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $logoImage = null;

    #[ORM\Column(length: 26, nullable: true)]
    private ?string $heroImage = null;

    #[ORM\Column]
    private bool $searchIndexing = false;

    #[ORM\Column(length: self::MAX_VERIFICATION_TOKEN, options: ['default' => ''])]
    private string $googleSiteVerification = '';

    private function __construct()
    {
        $this->legalIdentity = LegalIdentity::blank();
        $this->vatMentions = VatMentions::defaults();
        $this->seoTitle = new LocalizedText();
        $this->seoDescription = new LocalizedText();
    }

    public static function initial(): self
    {
        return new self();
    }

    public function switchSections(bool $lessons, bool $production, bool $beats): void
    {
        $this->lessonsEnabled = $lessons;
        $this->productionEnabled = $production;
        $this->beatsEnabled = $beats;
    }

    public function isEnabled(Section $section): bool
    {
        return match ($section) {
            Section::Lessons => $this->lessonsEnabled,
            Section::Production => $this->productionEnabled,
            Section::Beats => $this->beatsEnabled,
        };
    }

    public function enableOnlineCheckout(): void
    {
        if (!$this->legalIdentity->isComplete()) {
            throw new LegalIdentityIncomplete();
        }
        $this->onlineCheckout = true;
    }

    public function disableOnlineCheckout(): void
    {
        $this->onlineCheckout = false;
    }

    public function editLegalIdentity(LegalIdentity $identity): void
    {
        if ($this->onlineCheckout && !$identity->isComplete()) {
            throw new LegalIdentityIncomplete();
        }
        $this->legalIdentity = $identity;
    }

    public function editGeneral(string $contactEmail, ?string $bookingUrl, VatMentions $vatMentions, LocalizedText $seoTitle, LocalizedText $seoDescription, bool $searchIndexing, string $googleSiteVerification): void
    {
        $googleSiteVerification = trim($googleSiteVerification);
        if ('' !== $googleSiteVerification && 1 !== preg_match('/^[A-Za-z0-9_-]{10,'.self::MAX_VERIFICATION_TOKEN.'}$/', $googleSiteVerification)) {
            throw new InvalidVerificationToken();
        }
        $bookingUrl = HttpsUrl::orBlank($bookingUrl ?? '');
        $this->contactEmail = trim($contactEmail);
        $this->bookingUrl = '' === $bookingUrl ? null : $bookingUrl;
        $this->vatMentions = $vatMentions;
        $this->seoTitle = $seoTitle;
        $this->seoDescription = $seoDescription;
        $this->searchIndexing = $searchIndexing;
        $this->googleSiteVerification = $googleSiteVerification;
    }

    public function useImage(SiteImage $slot, string $image): ?string
    {
        return $this->place($slot, $image);
    }

    public function removeImage(SiteImage $slot): ?string
    {
        return $this->place($slot, null);
    }

    private function image(SiteImage $slot): ?string
    {
        return SiteImage::Logo === $slot ? $this->logoImage : $this->heroImage;
    }

    public function onlineCheckout(): bool
    {
        return $this->onlineCheckout;
    }

    public function contactEmail(): string
    {
        return $this->contactEmail;
    }

    public function bookingUrl(): ?string
    {
        return $this->bookingUrl;
    }

    public function legalIdentity(): LegalIdentity
    {
        return $this->legalIdentity;
    }

    public function vatMentions(): VatMentions
    {
        return $this->vatMentions;
    }

    public function seoTitle(): LocalizedText
    {
        return $this->seoTitle;
    }

    public function seoDescription(): LocalizedText
    {
        return $this->seoDescription;
    }

    public function logoImage(): ?string
    {
        return $this->logoImage;
    }

    public function heroImage(): ?string
    {
        return $this->heroImage;
    }

    public function searchIndexing(): bool
    {
        return $this->searchIndexing;
    }

    public function googleSiteVerification(): string
    {
        return $this->googleSiteVerification;
    }

    private function place(SiteImage $slot, ?string $image): ?string
    {
        $previous = $this->image($slot);
        if (SiteImage::Logo === $slot) {
            $this->logoImage = $image;
        } else {
            $this->heroImage = $image;
        }

        return $previous;
    }
}
