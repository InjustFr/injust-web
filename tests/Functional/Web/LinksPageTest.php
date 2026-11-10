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
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\SocialNetwork;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\SiteImage;
use App\Domain\Shared\LocalizedText;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class LinksPageTest extends WebTestCase
{
    use ConfiguresSite;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testTheButtonsFollowTheOrderGoThroughTheCounterAndLeaveHiddenLinksOut(): void
    {
        $client = self::createClient();
        $shop = self::bioLink('Boutique', 'Merch', 'https://shop.injust.test');
        $hidden = self::bioLink('Caché', 'Hidden', 'https://hidden.injust.test', visible: false);
        $album = self::bioLink('Album', 'Album', 'https://ffm.to/album', highlighted: true);
        self::getContainer()->get(OrderBioLinksHandler::class)(new OrderBioLinks([$album, $hidden, $shop]));

        $crawler = $client->request('GET', '/links');

        self::assertResponseIsSuccessful();
        $buttons = $crawler->filter('.links-list a');
        self::assertSame(['Album', 'Merch'], $buttons->each(static fn (Crawler $a): string => $a->text()));
        self::assertSame(['/go/'.$album, '/go/'.$shop], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame(['links-button links-button-primary', 'links-button'], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('class')));
        self::assertSame(['noopener', 'noopener'], $buttons->each(static fn (Crawler $a): string => (string) $a->attr('rel')));
        self::assertCount(0, $crawler->filter('main a[target]'));
        self::assertStringNotContainsString('hidden.injust.test', (string) $client->getResponse()->getContent());
        self::assertStringNotContainsString('shop.injust.test', (string) $client->getResponse()->getContent());
        self::assertCount(0, $crawler->filter('.links-empty'));
    }

    public function testLabelsAreInTheVisitorsLanguageAndFallBackToTheOther(): void
    {
        $client = self::createClient();
        self::bioLink('Écouter', 'Listen', 'https://ffm.to/a');
        self::bioLink('', 'Merch only in English', 'https://shop.injust.test');

        $crawler = $client->request('GET', '/fr/links');

        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame(['Écouter', 'Merch only in English'], $crawler->filter('.links-list a')->each(static fn (Crawler $a): string => $a->text()));
        self::assertSame(['Listen', 'Merch only in English'], $client->request('GET', '/links')->filter('.links-list a')->each(static fn (Crawler $a): string => $a->text()));
    }

    public function testALinkShowsItsNetworkIconOnlyWhenItHasOne(): void
    {
        $client = self::createClient();
        self::bioLink('Spotify', 'Spotify', 'https://open.spotify.com/artist/x', SocialNetwork::Spotify);
        self::bioLink('Site', 'Site', 'https://injust.test');

        $crawler = $client->request('GET', '/links');

        $buttons = $crawler->filter('.links-list a');
        self::assertSame('true', $buttons->eq(0)->filter('svg.links-icon')->attr('aria-hidden'));
        self::assertCount(0, $buttons->eq(1)->filter('svg'));
    }

    public function testAnUpcomingFeaturedReleaseAsksForAPreSaveStraightOnItsSmartLink(): void
    {
        $client = self::createClient();
        self::feature('We are fucked', '2026-12-04', 'https://ffm.to/wearefucked', cover: true);

        $crawler = $client->request('GET', '/links');

        $release = $crawler->filter('.links-release');
        self::assertSame('We are fucked', $release->filter('.links-release-title')->text());
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $release->filter('img')->attr('src'));
        self::assertSame(['links-release-cover', 'high'], [$release->filter('img')->attr('class'), $release->filter('img')->attr('fetchpriority')]);
        self::assertMatchesRegularExpression('/^[1-9]\d*x[1-9]\d*$/', $release->filter('img')->attr('width').'x'.$release->filter('img')->attr('height'));
        self::assertNotSame(['240', '240'], [$release->filter('img')->attr('width'), $release->filter('img')->attr('height')]);
        $cta = $release->filter('a.button');
        self::assertSame(['Pre-save', 'https://ffm.to/wearefucked', 'noopener'], [$cta->text(), $cta->attr('href'), $cta->attr('rel')]);
    }

    public function testAReleasedFeaturedReleaseIsToBeListenedTo(): void
    {
        $client = self::createClient();
        self::feature('This is discrimination', '2026-11-06', 'https://ffm.to/discrimination');

        $crawler = $client->request('GET', '/fr/links');

        $release = $crawler->filter('.links-release');
        self::assertCount(0, $release->filter('img'));
        self::assertSame(['Écouter', 'https://ffm.to/discrimination'], [$release->filter('a.button')->text(), $release->filter('a.button')->attr('href')]);
    }

    public function testAFeaturedReleaseWithoutASmartLinkHasNoCallToAction(): void
    {
        $client = self::createClient();
        self::feature('Draft', '2026-12-01', null);

        $crawler = $client->request('GET', '/links');

        self::assertSame('Draft', $crawler->filter('.links-release-title')->text());
        self::assertCount(0, $crawler->filter('.links-release a'));
    }

    public function testTheSocialLinksAreIconsWithAccessibleNames(): void
    {
        $client = self::createClient();
        self::getContainer()->get(AddSocialLinkHandler::class)(new AddSocialLink(SocialNetwork::Instagram, 'https://instagram.com/injust', null));
        self::getContainer()->get(AddSocialLinkHandler::class)(new AddSocialLink(SocialNetwork::Other, 'https://injust.bandcamp.test', 'Bandcamp shop'));

        $crawler = $client->request('GET', '/links');

        $socials = $crawler->filter('.links-socials a');
        self::assertSame(['Instagram', 'Bandcamp shop'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('aria-label')));
        self::assertSame(['https://instagram.com/injust', 'https://injust.bandcamp.test'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('href')));
        self::assertSame(['me noopener', 'me noopener'], $socials->each(static fn (Crawler $a): string => (string) $a->attr('rel')));
        self::assertCount(2, $socials->filter('svg[aria-hidden="true"]'));
    }

    public function testAnEmptyPageShowsTheWordmarkTheTaglineAndSaysSo(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/links');

        self::assertResponseIsSuccessful();
        self::assertSame('INJUST', $crawler->filter('h1')->text());
        self::assertSame('Injust: protest punk and hard rock.', $crawler->filter('.links-tagline')->text());
        self::assertSame('No links yet. The full site has everything.', $crawler->filter('.links-empty')->text());
        self::assertCount(0, $crawler->filter('.links-list, .links-release, .links-socials'));
        self::assertCount(0, $crawler->filter('.site-header'));
    }

    public function testTheLogoAndTheTaglineComeFromTheAdmin(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Logo);
        self::getContainer()->get(EditMusicTextHandler::class)(new EditMusicText(new LocalizedText('Du punk qui dérange.', 'Punk that bothers.'), new LocalizedText(), new LocalizedText(), []));

        $crawler = $client->request('GET', '/fr/links');

        self::assertSame('Injust', $crawler->filter('h1 img')->attr('alt'));
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $crawler->filter('h1 img')->attr('src'));
        self::assertMatchesRegularExpression('/^[1-9]\d*x[1-9]\d*$/', $crawler->filter('h1 img')->attr('width').'x'.$crawler->filter('h1 img')->attr('height'));
        self::assertSame('Du punk qui dérange.', $crawler->filter('.links-tagline')->text());
    }

    public function testEveryNetworkHasAnIcon(): void
    {
        $client = self::createClient();
        foreach (SocialNetwork::cases() as $network) {
            self::bioLink($network->value, $network->value, 'https://injust.test/'.$network->value, $network);
        }

        $crawler = $client->request('GET', '/links');

        self::assertSame(
            array_fill(0, \count(SocialNetwork::cases()), true),
            $crawler->filter('.links-list a')->each(static fn (Crawler $a): bool => $a->filter('svg.links-icon path')->count() > 0),
        );
    }

    public function testThePageStartsNoSessionAndLeadsBackToTheSite(): void
    {
        $client = self::createClient();
        self::writeLegal(LegalKind::Mentions, 'Mentions');
        self::writeLegal(LegalKind::Privacy, 'Confidentialité');

        $crawler = $client->request('GET', '/links');

        self::assertResponseIsSuccessful();
        self::assertSame([], $client->getResponse()->headers->getCookies());
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
        self::assertSame('Links | Injust', $crawler->filter('title')->text());
        $footer = $crawler->filter('.links-footer a');
        self::assertSame(
            [['Full site', '/'], ['Contact', '/contact'], ['Legal notice', '/legal/mentions'], ['FR', '/fr/links']],
            $footer->each(static fn (Crawler $a): array => [$a->text(), (string) $a->attr('href')]),
        );
        $home = $client->request('GET', '/fr/links')->filter('.links-footer a')->first();
        self::assertSame(['Site complet', '/fr'], [$home->text(), $home->attr('href')]);
    }

    public function testTheSitemapListsTheLinksPageInBothLanguages(): void
    {
        $client = self::createClient();

        $client->request('GET', '/sitemap.xml');

        $sitemap = new \SimpleXMLElement((string) $client->getResponse()->getContent());
        $locs = [];
        foreach ($sitemap->url as $url) {
            $locs[] = (string) $url->loc;
        }
        self::assertContains('http://localhost/links', $locs);
        self::assertContains('http://localhost/fr/links', $locs);
    }

    private static function bioLink(string $fr, string $en, string $url, ?SocialNetwork $icon = null, bool $highlighted = false, bool $visible = true): string
    {
        return self::getContainer()->get(AddBioLinkHandler::class)(new AddBioLink(new LocalizedText($fr, $en), $url, $icon, $highlighted, $visible))->id;
    }

    private static function feature(string $title, string $date, ?string $smartLinkUrl, bool $cover = false): void
    {
        $release = self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            ReleaseType::Single,
            new \DateTimeImmutable($date),
            new LocalizedText(),
            [],
            [],
            $smartLinkUrl,
            null,
        )));
        $id = Ulid::fromString($release->id);
        if ($cover) {
            self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover($id, \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));
        }
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease($id, true));
    }
}
