<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Seo\EditPageSeo\EditPageSeo;
use App\Application\Seo\EditPageSeo\EditPageSeoHandler;
use App\Application\Seo\UploadPageSeoImage\UploadPageSeoImage;
use App\Application\Seo\UploadPageSeoImage\UploadPageSeoImageHandler;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettings;
use App\Application\Settings\EditGeneralSettings\EditGeneralSettingsHandler;
use App\Domain\Seo\PageKey;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\SiteImage;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\LocalizedText;
use App\Tests\Support\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class SeoMetaTest extends WebTestCase
{
    use ConfiguresSite;

    /** @return iterable<string, array{string, string, string, string}> */
    public static function pages(): iterable
    {
        yield 'home' => ['/', 'http://localhost/', 'en_GB', 'fr_FR'];
        yield 'french home' => ['/fr', 'http://localhost/fr', 'fr_FR', 'en_GB'];
        yield 'lessons' => ['/lessons', 'http://localhost/lessons', 'en_GB', 'fr_FR'];
        yield 'production' => ['/fr/production', 'http://localhost/fr/production', 'fr_FR', 'en_GB'];
        yield 'contact with a query' => ['/contact?topic=lessons&offer=x', 'http://localhost/contact', 'en_GB', 'fr_FR'];
        yield 'links' => ['/fr/links', 'http://localhost/fr/links', 'fr_FR', 'en_GB'];
        yield 'legal' => ['/legal/mentions', 'http://localhost/legal/mentions', 'en_GB', 'fr_FR'];
    }

    #[DataProvider('pages')]
    public function testEveryPublicPageCarriesACompleteHead(string $url, string $canonical, string $locale, string $alternate): void
    {
        $client = self::createClient();
        self::switchSections(true, true, false);
        self::writeLegal(LegalKind::Mentions, 'Mentions', 'Legal notice');

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        $title = $crawler->filter('title')->text();
        $description = (string) $crawler->filter('meta[name="description"]')->attr('content');
        self::assertNotSame('', $title);
        self::assertNotSame('', $description);
        self::assertSame($canonical, $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertCount(3, $crawler->filter('link[rel="alternate"][hreflang]'));
        self::assertSame($canonical, self::meta($crawler, 'og:url'));
        self::assertSame('website', self::meta($crawler, 'og:type'));
        self::assertSame('Injust', self::meta($crawler, 'og:site_name'));
        self::assertSame($title, self::meta($crawler, 'og:title'));
        self::assertSame($description, self::meta($crawler, 'og:description'));
        self::assertSame($locale, self::meta($crawler, 'og:locale'));
        self::assertSame($alternate, self::meta($crawler, 'og:locale:alternate'));
        self::assertSame('http://localhost/og-default.png', self::meta($crawler, 'og:image'));
        self::assertSame('1200', self::meta($crawler, 'og:image:width'));
        self::assertSame('630', self::meta($crawler, 'og:image:height'));
        self::assertSame($title, self::meta($crawler, 'og:image:alt'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        self::assertCount(0, $crawler->filter('meta[name="google-site-verification"]'));
        $graph = self::graph($crawler);
        self::assertSame('MusicGroup', Json::string($graph, 0, '@type'));
        $types = array_map(static fn (mixed $node): string => Json::string($node, '@type'), $graph);
        $home = \in_array($url, ['/', '/fr'], true);
        self::assertSame($home, \in_array('WebSite', $types, true));
        self::assertSame(!$home, \in_array('BreadcrumbList', $types, true));
    }

    public function testASubpageTellsSearchEnginesWhereItSits(): void
    {
        $client = self::createClient();
        self::writeLegal(LegalKind::Mentions, 'Mentions', 'Legal notice');

        $crawler = $client->request('GET', '/fr/legal/mentions');

        self::assertSame([
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Injust', 'item' => 'http://localhost/fr'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Mentions légales', 'item' => 'http://localhost/fr/legal/mentions'],
            ],
        ], Json::array(self::graph($crawler), 1));
    }

    public function testThePageSeoOverridesTheTitleDescriptionAndSharingImage(): void
    {
        $client = self::createClient();
        self::switchSections(true, false, false);
        self::upload(SiteImage::Hero);
        self::getContainer()->get(EditPageSeoHandler::class)(new EditPageSeo(
            PageKey::Lessons,
            new LocalizedText('Cours de guitare punk à Paris', ''),
            new LocalizedText('Riffs, son et scène.', 'Riffs, tone and stage.'),
        ));
        $image = self::getContainer()->get(UploadPageSeoImageHandler::class)(new UploadPageSeoImage(PageKey::Lessons, \dirname(__DIR__, 2).'/Fixtures/large-fern.png'))->image;

        $french = $client->request('GET', '/fr/lessons');
        $english = $client->request('GET', '/lessons');

        self::assertSame('Cours de guitare punk à Paris', $french->filter('title')->text());
        self::assertSame('Riffs, son et scène.', $french->filter('meta[name="description"]')->attr('content'));
        self::assertSame('Cours de guitare punk à Paris', $english->filter('title')->text());
        self::assertSame('Riffs, tone and stage.', self::meta($english, 'og:description'));
        self::assertSame('http://localhost/media/images/'.$image.'.webp', self::meta($english, 'og:image'));
        $size = getimagesize((string) self::$kernel?->getShareDir().'/images/'.$image.'.webp');
        self::assertIsArray($size);
        self::assertSame([(string) $size[0], (string) $size[1]], [self::meta($english, 'og:image:width'), self::meta($english, 'og:image:height')]);
    }

    public function testWithoutAnOverrideASubpageKeepsItsTitlePatternAndSharesTheHero(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);
        self::getContainer()->get(EditPageSeoHandler::class)(new EditPageSeo(PageKey::Contact, new LocalizedText(), new LocalizedText()));

        $crawler = $client->request('GET', '/contact');

        self::assertSame('Contact | Injust', $crawler->filter('title')->text());
        self::assertNotSame('', $crawler->filter('meta[name="description"]')->attr('content'));
        self::assertMatchesRegularExpression('#^http://localhost/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', self::meta($crawler, 'og:image'));
        self::assertMatchesRegularExpression('/^[1-9]\d*$/', self::meta($crawler, 'og:image:width'));
    }

    public function testTheSearchConsoleTokenIsPublishedOnceSet(): void
    {
        $client = self::createClient();
        self::getContainer()->get(EditGeneralSettingsHandler::class)(new EditGeneralSettings(
            '',
            null,
            new VatMentions('', '', ''),
            new LocalizedText(),
            new LocalizedText(),
            true,
            'abcDEF123_-xyz',
        ));

        $crawler = $client->request('GET', '/fr/contact');

        self::assertSame('abcDEF123_-xyz', $crawler->filter('meta[name="google-site-verification"]')->attr('content'));
    }

    public function testTheDisplayFontAndTheIconsAreAnnounced(): void
    {
        $client = self::createClient();

        $crawler = $client->request('GET', '/');

        $font = $crawler->filter('link[rel="preload"][as="font"]');
        self::assertCount(1, $font);
        self::assertSame('font/woff2', $font->attr('type'));
        self::assertSame('', $font->attr('crossorigin'));
        self::assertMatchesRegularExpression('#/anton-latin-400-normal[-\w]*\.woff2$#', (string) $font->attr('href'));
        self::assertSame('/apple-touch-icon.png', $crawler->filter('link[rel="apple-touch-icon"]')->attr('href'));
        self::assertSame('/site.webmanifest', $crawler->filter('link[rel="manifest"]')->attr('href'));
    }

    public function testTheIconsTheManifestAndTheDefaultSharingImageExist(): void
    {
        $public = \dirname(__DIR__, 3).'/public';

        foreach (['apple-touch-icon.png' => 180, 'icon-192.png' => 192, 'icon-512.png' => 512] as $file => $side) {
            $size = getimagesize($public.'/'.$file);
            self::assertIsArray($size, $file);
            self::assertSame([$side, $side, \IMAGETYPE_PNG], [$size[0], $size[1], $size[2]], $file);
        }
        $size = getimagesize($public.'/og-default.png');
        self::assertIsArray($size);
        self::assertSame([1200, 630, \IMAGETYPE_PNG], [$size[0], $size[1], $size[2]]);
        $manifest = Json::decode((string) file_get_contents($public.'/site.webmanifest'));
        self::assertSame('Injust', Json::string($manifest, 'name'));
        self::assertSame('/', Json::string($manifest, 'start_url'));
        self::assertSame(['/icon-192.png', '/icon-512.png'], array_map(static fn (mixed $icon): string => Json::string($icon, 'src'), Json::array($manifest, 'icons')));
    }

    private static function meta(Crawler $crawler, string $property): string
    {
        $node = $crawler->filter('meta[property="'.$property.'"]');
        self::assertCount(1, $node, $property);

        return (string) $node->attr('content');
    }

    /** @return array<mixed> */
    private static function graph(Crawler $crawler): array
    {
        $script = $crawler->filter('script[type="application/ld+json"]');
        self::assertCount(1, $script);
        $data = Json::decode($script->text());
        self::assertSame('https://schema.org', Json::string($data, '@context'));

        return Json::array($data, '@graph');
    }
}
