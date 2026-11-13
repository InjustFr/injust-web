<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Links\AddBioLink\AddBioLink;
use App\Application\Links\AddBioLink\AddBioLinkHandler;
use App\Application\Links\OrderBioLinks\OrderBioLinks;
use App\Application\Links\OrderBioLinks\OrderBioLinksHandler;
use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\AddSocialLink\AddSocialLink;
use App\Application\Music\AddSocialLink\AddSocialLinkHandler;
use App\Application\Music\EditMusicText\EditMusicText;
use App\Application\Music\EditMusicText\EditMusicTextHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class HomeLinksTest extends WebTestCase
{
    use ConfiguresSite;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testTheLinksComeRightAfterTheHeroUnderAClearHeading(): void
    {
        $client = self::createClient();
        self::bioLink('Album', 'Album', 'https://ffm.to/album');
        self::text(['Hook one']);

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['hero', 'links'], \array_slice($crawler->filter('main > section')->each(static fn (Crawler $section): string => (string) ($section->attr('id') ?? $section->attr('class'))), 0, 2));
        self::assertSame(['Listen & follow'], $crawler->filter('#links h2')->each(static fn (Crawler $h2): string => $h2->text()));
        self::assertSame('links-title', $crawler->filter('#links')->attr('aria-labelledby'));
        self::assertSame('Écouter & suivre', $client->request('GET', '/fr')->filter('#links h2')->text());
    }

    public function testTheButtonsFollowTheOrderGoThroughTheCounterAndLeaveHiddenLinksOut(): void
    {
        $client = self::createClient();
        $shop = self::bioLink('Boutique', 'Merch', 'https://shop.injust.test');
        $hidden = self::bioLink('Caché', 'Hidden', 'https://hidden.injust.test', visible: false);
        $album = self::bioLink('Album', 'Album', 'https://ffm.to/album', highlighted: true);
        self::getContainer()->get(OrderBioLinksHandler::class)(new OrderBioLinks([$album, $hidden, $shop]));

        $crawler = $client->request('GET', '/');

        $buttons = $crawler->filter('#links .links-list a');
        self::assertSame(['Album', 'Merch'], $buttons->each(static fn (Crawler $a): string => $a->text()));
        self::assertSame(['/go/'.$album, '/go/'.$shop], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame(['links-button links-button-primary', 'links-button'], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('class')));
        self::assertSame(['noopener', 'noopener'], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('rel')));
        self::assertCount(0, $crawler->filter('#links a[target]'));
        self::assertStringNotContainsString('hidden.injust.test', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('shop.injust.test', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filter('.hero-socials'));
    }

    public function testLabelsAreInTheVisitorsLanguageAndFallBackToTheOther(): void
    {
        $client = self::createClient();
        self::bioLink('Écouter', 'Listen', 'https://ffm.to/a');
        self::bioLink('', 'Merch only in English', 'https://shop.injust.test');

        $crawler = $client->request('GET', '/fr');

        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame(['Écouter', 'Merch only in English'], $crawler->filter('#links .links-list a')->each(static fn (Crawler $a): string => $a->text()));
        self::assertSame(['Listen', 'Merch only in English'], $client->request('GET', '/')->filter('#links .links-list a')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testALinkShowsItsNetworkIconOnlyWhenItHasOne(): void
    {
        $client = self::createClient();
        self::bioLink('Spotify', 'Spotify', 'https://open.spotify.com/artist/x', SocialNetwork::Spotify);
        self::bioLink('Site', 'Site', 'https://injust.test');

        $crawler = $client->request('GET', '/');

        $buttons = $crawler->filter('#links .links-list a');
        self::assertSame('true', $buttons->eq(0)->filter('svg.links-icon')->attr('aria-hidden'));
        self::assertCount(0, $buttons->eq(1)->filter('svg'));
    }

    public function testEveryNetworkHasAnIcon(): void
    {
        $client = self::createClient();
        foreach (SocialNetwork::cases() as $network) {
            self::bioLink($network->value, $network->value, 'https://injust.test/'.$network->value, $network);
        }

        $crawler = $client->request('GET', '/');

        self::assertSame(
            array_fill(0, \count(SocialNetwork::cases()), true),
            $crawler->filter('#links .links-list a')->each(static fn (Crawler $a): bool => $a->filter('svg.links-icon path')->count() > 0),
        );
    }

    public function testTheSocialLinksAreIconsInTheHeroNamedByTheirLabelOrTheirNetwork(): void
    {
        $client = self::createClient();
        self::social(SocialNetwork::Instagram, 'https://instagram.com/injust', null);
        self::social(SocialNetwork::Other, 'https://injust.example/press', 'Press kit');

        $crawler = $client->request('GET', '/');

        $socials = $crawler->filter('.hero .hero-socials a');
        self::assertSame(['Instagram', 'Press kit'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('aria-label')));
        self::assertSame(['https://instagram.com/injust', 'https://injust.example/press'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame(['me noopener', 'me noopener'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('rel')));
        self::assertCount(2, $socials->filter('svg[aria-hidden="true"]'));
    }

    public function testTheSocialLinksAreListedUnderFollowNextToTheBioLinks(): void
    {
        $client = self::createClient();
        self::bioLink('Album', 'Album', 'https://ffm.to/album');
        self::social(SocialNetwork::Instagram, 'https://instagram.com/injust', null);
        self::social(SocialNetwork::Other, 'https://injust.example/press', 'Press kit');

        $crawler = $client->request('GET', '/');

        self::assertSame(['Listen', 'Follow'], $crawler->filter('#links h3')->each(static fn (Crawler $h3): string => $h3->text()));
        self::assertSame(['Album'], $crawler->filter('#links .links-list a')->each(static fn (Crawler $a): string => $a->text()));
        $follow = $crawler->filter('#links .links-socials a');
        self::assertSame([['Instagram', 'https://instagram.com/injust', 'me noopener'], ['Press kit', 'https://injust.example/press', 'me noopener']], $follow->each(static fn (Crawler $a): array => [$a->text(), (string) $a->attr('href'), (string) $a->attr('rel')]));
        self::assertSame(['Écouter', 'Suivre'], $client->request('GET', '/fr')->filter('#links h3')->each(static fn (Crawler $h3): string => $h3->text()));
    }

    public function testWithOnlySocialLinksTheBlockShowsFollowAndTheHeroLeadsToIt(): void
    {
        $client = self::createClient();
        self::social(SocialNetwork::Instagram, 'https://instagram.com/injust', null);

        $crawler = $client->request('GET', '/');

        self::assertSame(['Follow'], $crawler->filter('#links h3')->each(static fn (Crawler $h3): string => $h3->text()));
        self::assertCount(0, $crawler->filter('#links .links-list'));
        self::assertSame(['Links'], $crawler->filter('.hero a[href="#links"]')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testTheHeroJumpsToTheLinksNextToItsMainCallToAction(): void
    {
        $client = self::createClient();
        self::bioLink('Album', 'Album', 'https://ffm.to/album');
        self::feature('Riot', 'https://ffm.to/riot');

        $crawler = $client->request('GET', '/');

        $actions = $crawler->filter('.hero-actions a');
        self::assertSame([['Listen', 'https://ffm.to/riot', 'button'], ['Links', '#links', 'button-ghost']], $actions->each(static fn (Crawler $a): array => [$a->text(), (string) $a->attr('href'), (string) $a->attr('class')]));
        self::assertSame('Liens', $client->request('GET', '/fr')->filter('.hero a[href="#links"]')->text());
    }

    public function testWithoutAFeaturedReleaseTheHeroStillLeadsToTheLinks(): void
    {
        $client = self::createClient();
        self::bioLink('Boutique', 'Merch', 'https://shop.injust.test');

        $crawler = $client->request('GET', '/');

        self::assertSame(['Links'], $crawler->filter('.hero a[href="#links"]')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testWithoutAnyLinkThereIsNoBlockAndNoJump(): void
    {
        $client = self::createClient();
        self::bioLink('Caché', 'Hidden', 'https://hidden.injust.test', visible: false);
        self::feature('Riot', 'https://ffm.to/riot');

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#links, a[href="#links"]'));
        self::assertSame(['Listen'], $crawler->filter('.hero-actions a')->each(static fn (Crawler $a): string => $a->text()));
    }

    #[TestWith(['/links'])]
    #[TestWith(['/fr/links'])]
    public function testTheSeparateLinksPageIsGone(string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $url);

        self::assertResponseStatusCodeSame(404);
    }

    private static function bioLink(string $fr, string $en, string $url, ?SocialNetwork $icon = null, bool $highlighted = false, bool $visible = true): string
    {
        return self::getContainer()->get(AddBioLinkHandler::class)(new AddBioLink(new LocalizedText($fr, $en), $url, $icon, $highlighted, $visible))->id;
    }

    private static function social(SocialNetwork $network, string $url, ?string $label): void
    {
        self::getContainer()->get(AddSocialLinkHandler::class)(new AddSocialLink($network, $url, $label));
    }

    /** @param list<string> $hooks */
    private static function text(array $hooks): void
    {
        self::getContainer()->get(EditMusicTextHandler::class)(new EditMusicText(new LocalizedText(), new LocalizedText(), new LocalizedText(), $hooks));
    }

    private static function feature(string $title, string $smartLinkUrl): void
    {
        $release = self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            ReleaseType::Single,
            new \DateTimeImmutable('2026-11-06'),
            new LocalizedText(),
            [],
            [],
            $smartLinkUrl,
            null,
        )));
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease(Ulid::fromString($release->id), true));
    }
}
