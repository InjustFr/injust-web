<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Settings;

use App\Domain\Settings\Exception\InvalidVerificationToken;
use App\Domain\Settings\Exception\LegalIdentityIncomplete;
use App\Domain\Settings\Section;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SiteImage;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Exception\InvalidUrl;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testTheSiteStartsWithEverySectionAndCheckoutOff(): void
    {
        $settings = Settings::initial();

        foreach (Section::cases() as $section) {
            self::assertFalse($settings->isEnabled($section));
        }
        self::assertFalse($settings->onlineCheckout());
        self::assertFalse($settings->searchIndexing());
        self::assertSame(['', null, null, null], [$settings->contactEmail(), $settings->bookingUrl(), $settings->logoImage(), $settings->heroImage()]);
        self::assertSame('EI', $settings->legalIdentity()->businessForm);
    }

    public function testTheVatMentionsStartWithTheFrenchFranchiseWording(): void
    {
        $mentions = Settings::initial()->vatMentions();

        self::assertSame(['TVA non applicable – art. 293 B du CGI', 'Autoliquidation', 'TVA non applicable – art. 259-1 du CGI'], [$mentions->consumer, $mentions->euBusiness, $mentions->nonEuBusiness]);
    }

    public function testSectionsAreSwitchedTogether(): void
    {
        $settings = Settings::initial();

        $settings->switchSections(lessons: true, production: false, beats: true);

        self::assertSame([true, false, true], array_map($settings->isEnabled(...), [Section::Lessons, Section::Production, Section::Beats]));
    }

    public function testCheckoutCannotBeSwitchedOnWithoutAFullLegalIdentity(): void
    {
        $this->expectException(LegalIdentityIncomplete::class);

        Settings::initial()->enableOnlineCheckout();
    }

    public function testCheckoutNeedsTheHostPhone(): void
    {
        $settings = Settings::initial();
        $settings->editLegalIdentity(LegalIdentityTest::complete(hostPhone: ''));

        $this->expectException(LegalIdentityIncomplete::class);

        $settings->enableOnlineCheckout();
    }

    public function testCheckoutIsSwitchedOnOnceTheIdentityIsComplete(): void
    {
        $settings = Settings::initial();
        $settings->editLegalIdentity(LegalIdentityTest::complete());

        $settings->enableOnlineCheckout();

        self::assertTrue($settings->onlineCheckout());
        $settings->disableOnlineCheckout();
        self::assertFalse($settings->onlineCheckout());
    }

    public function testAPartialIdentityIsSavedWhileCheckoutIsOff(): void
    {
        $settings = Settings::initial();

        $settings->editLegalIdentity(LegalIdentityTest::complete(phone: ''));

        self::assertSame('', $settings->legalIdentity()->phone);
        self::assertSame('Louis Injust', $settings->legalIdentity()->name);
    }

    public function testTheIdentityCannotBecomeIncompleteWhileCheckoutIsOn(): void
    {
        $settings = Settings::initial();
        $settings->editLegalIdentity(LegalIdentityTest::complete());
        $settings->enableOnlineCheckout();

        $this->expectException(LegalIdentityIncomplete::class);

        $settings->editLegalIdentity(LegalIdentityTest::complete(phone: ''));
    }

    public function testGeneralSettingsAreEditedTogether(): void
    {
        $settings = Settings::initial();
        $mentions = new VatMentions('A', 'B', 'C');

        $settings->editGeneral(' hello@injust.test ', 'https://cal.test/injust', $mentions, new LocalizedText('Titre', 'Title'), new LocalizedText('', 'Description'), true, '');

        self::assertSame(['hello@injust.test', 'https://cal.test/injust', true], [$settings->contactEmail(), $settings->bookingUrl(), $settings->searchIndexing()]);
        self::assertSame($mentions, $settings->vatMentions());
        self::assertSame(['Titre', 'Description'], [$settings->seoTitle()->in('fr'), $settings->seoDescription()->in('fr')]);
    }

    public function testABlankBookingUrlMeansNone(): void
    {
        $settings = Settings::initial();

        $settings->editGeneral('', '  ', VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, '');

        self::assertNull($settings->bookingUrl());
    }

    public function testTheBookingUrlMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        Settings::initial()->editGeneral('', 'http://cal.test', VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, '');
    }

    public function testEachImageReturnsTheOneItReplaces(): void
    {
        $settings = Settings::initial();

        self::assertNull($settings->useImage(SiteImage::Logo, 'LOGO1'));
        self::assertSame('LOGO1', $settings->useImage(SiteImage::Logo, 'LOGO2'));
        self::assertNull($settings->useImage(SiteImage::Hero, 'HERO1'));

        self::assertSame(['LOGO2', 'HERO1'], [$settings->logoImage(), $settings->heroImage()]);
        self::assertSame('HERO1', $settings->removeImage(SiteImage::Hero));
        self::assertNull($settings->heroImage());
    }

    public function testTheGoogleVerificationTokenStartsBlankAndIsKeptTrimmed(): void
    {
        $settings = Settings::initial();
        self::assertSame('', $settings->googleSiteVerification());

        foreach ([' abcdefghij ', 'aZ09_-'.str_repeat('x', 94)] as $token) {
            $settings->editGeneral('', null, VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, $token);
            self::assertSame(trim($token), $settings->googleSiteVerification());
        }

        $settings->editGeneral('', null, VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, '');
        self::assertSame('', $settings->googleSiteVerification());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTokens(): iterable
    {
        yield 'too short' => ['abcdefghi'];
        yield 'too long' => [str_repeat('a', 101)];
        yield 'a whole meta tag' => ['<meta name="google-site-verification" content="abcdefghijkl">'];
        yield 'a space inside' => ['abcde fghijk'];
        yield 'a sign before' => ['!abcdefghijk'];
        yield 'a sign after' => ['abcdefghijk!'];
    }

    #[DataProvider('invalidTokens')]
    public function testAnythingButAVerificationTokenIsRefused(string $token): void
    {
        $settings = Settings::initial();

        try {
            $settings->editGeneral('', null, VatMentions::defaults(), new LocalizedText(), new LocalizedText(), false, $token);
            self::fail('An invalid verification token was accepted.');
        } catch (InvalidVerificationToken) {
            self::assertSame('', $settings->googleSiteVerification());
        }
    }
}
