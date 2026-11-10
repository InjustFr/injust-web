<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Domain\Settings\LegalKind;
use App\Domain\Settings\SiteImage;
use App\Tests\Support\Json;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SitePagesTest extends WebTestCase
{
    use ConfiguresSite;

    public function testTheHomePageNeedsNoSession(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('INJUST', $crawler->filter('header .wordmark')->text());
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertNull($client->getCookieJar()->get('PHPSESSID'));
    }

    public function testTheFrenchHomeIsServedUnderFrWhateverTheBrowserPrefers(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/fr', server: ['HTTP_ACCEPT_LANGUAGE' => 'en']);

        self::assertResponseIsSuccessful();
        self::assertSame('fr', $crawler->filter('html')->attr('lang'));
        self::assertSame('http://localhost/fr', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('Passer au contenu', $crawler->filter('.skip-link')->text());
    }

    public function testTheEnglishHomeIgnoresAFrenchBrowser(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);

        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertSame('Skip to content', $crawler->filter('.skip-link')->text());
    }

    public function testEveryPageLinksItsTranslation(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::writeLegal(LegalKind::Mentions, 'Mentions');

        foreach (['/' => '/fr', '/lessons' => '/fr/lessons', '/legal/mentions' => '/fr/legal/mentions'] as $en => $fr) {
            foreach ([$en, $fr] as $url) {
                $crawler = $client->request('GET', $url);
                self::assertResponseIsSuccessful();
                self::assertSame('http://localhost'.$en, $crawler->filter('link[rel="alternate"][hreflang="en"]')->attr('href'));
                self::assertSame('http://localhost'.$fr, $crawler->filter('link[rel="alternate"][hreflang="fr"]')->attr('href'));
                self::assertSame('http://localhost'.$en, $crawler->filter('link[rel="alternate"][hreflang="x-default"]')->attr('href'));
                self::assertSame('http://localhost'.$url, $crawler->filter('link[rel="canonical"]')->attr('href'));
            }
            $crawler = $client->request('GET', $en);
            self::assertSame($fr, $crawler->filter('a[hreflang="fr"]')->attr('href'));
            $crawler = $client->request('GET', $fr);
            self::assertSame($en, $crawler->filter('a[hreflang="en"]')->attr('href'));
        }
    }

    public function testTheNavListsOnlyTheSwitchedOnSections(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');
        self::assertCount(0, $crawler->filter('header nav a.nav-section'));

        self::switchSections(true, false, true);
        $crawler = $client->request('GET', '/fr');
        self::assertSame(['/fr/lessons', '/fr/beats'], $crawler->filter('header nav a.nav-section')->each(static fn ($link) => $link->attr('href')));
        self::assertSame(['Cours', 'Beats'], $crawler->filter('header nav a.nav-section')->each(static fn ($link) => $link->text()));
    }

    public function testTheCurrentSectionIsMarkedInTheNav(): void
    {
        $client = self::createClient();
        self::switchSections(true, true, false);

        $crawler = $client->request('GET', '/production');

        self::assertSame('page', $crawler->filter('header nav a[href="/production"]')->attr('aria-current'));
        self::assertNull($crawler->filter('header nav a[href="/lessons"]')->attr('aria-current'));
    }

    public function testTheLogoReplacesTheTextWordmark(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Logo);

        $crawler = $client->request('GET', '/');

        $logo = $crawler->filter('header .wordmark img');
        self::assertSame('Injust', $logo->attr('alt'));
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $logo->attr('src'));
    }

    public function testTheHeroShowsTheSettingsPhoto(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');
        self::assertCount(0, $crawler->filter('.hero img'));

        self::upload(SiteImage::Hero);
        $crawler = $client->request('GET', '/');
        self::assertMatchesRegularExpression('#^/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $crawler->filter('.hero img')->attr('src'));
        self::assertSame('INJUST', $crawler->filter('.hero h1')->text());
    }

    public function testTheFooterLinksTheLegalPagesOfTheSwitchedOnSectionsAndTheContactEmail(): void
    {
        $client = self::createClient();
        self::editGeneral(contactEmail: 'hello@injust.test');
        foreach (LegalKind::cases() as $kind) {
            self::writeLegal($kind, 'Texte');
        }
        self::switchSections(false, true, false);

        $crawler = $client->request('GET', '/');

        self::assertSame(['/legal/mentions', '/legal/privacy', '/legal/cgv-production'], $crawler->filter('footer a.legal-link')->each(static fn ($link) => $link->attr('href')));
        self::assertSame('mailto:hello@injust.test', $crawler->filter('footer a[href^="mailto:"]')->attr('href'));
    }

    public function testTheFooterSkipsLegalPagesNotWrittenYet(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        self::assertCount(0, $crawler->filter('footer a.legal-link'));
        self::assertCount(0, $crawler->filter('footer a[href^="mailto:"]'));
    }

    public function testTheSeoTextsComeFromTheSettings(): void
    {
        $client = self::createClient();
        self::editGeneral();

        $crawler = $client->request('GET', '/fr');

        self::assertSelectorTextSame('title', 'Injust — punk de protestation');
        self::assertSame('Punk de protestation.', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertSame('fr_FR', $crawler->filter('meta[property="og:locale"]')->attr('content'));
        $graph = Json::array(Json::decode($crawler->filter('script[type="application/ld+json"]')->text()), '@graph');
        self::assertSame('MusicGroup', Json::string($graph, 0, '@type'));
        self::assertSame('fr', Json::string($graph, 1, 'inLanguage'));
    }

    public function testPagesStayUpWhenTheSameKernelServesSeveralRequests(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        self::switchSections(true, true, true);

        foreach (['/', '/fr', '/lessons', '/fr/beats', '/', '/production'] as $url) {
            $client->request('GET', $url);
            self::assertResponseIsSuccessful();
        }
    }
}
