<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\LocalizedText;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class SitemapTest extends WebTestCase
{
    use ConfiguresSite;

    private const string XHTML = 'http://www.w3.org/1999/xhtml';
    private const string IMAGE = 'http://www.google.com/schemas/sitemap-image/1.1';

    public function testEveryPageNamesBothLanguagesAndTheEnglishDefault(): void
    {
        $client = self::createClient();

        $client->request('GET', '/sitemap.xml');

        $urls = self::urls($client->getResponse()->getContent());
        self::assertSame(['http://localhost/', 'http://localhost/fr', 'http://localhost/contact', 'http://localhost/fr/contact'], array_keys($urls));
        foreach ($urls as $url) {
            $alternates = [];
            foreach ($url->children(self::XHTML)->link as $link) {
                $alternates[(string) $link->attributes()?->hreflang] = (string) $link->attributes()?->href;
            }
            self::assertSame(['en', 'fr', 'x-default'], array_keys($alternates));
            self::assertSame($alternates['en'], $alternates['x-default']);
            self::assertSame('', (string) $url->lastmod);
        }
    }

    public function testALegalPageIsDatedByItsLastEdit(): void
    {
        $client = self::createClient();
        Clock::set(new MockClock('2026-10-02 14:30:00', 'UTC'));
        self::writeLegal(LegalKind::Mentions, 'Mentions');

        $client->request('GET', '/sitemap.xml');

        $urls = self::urls($client->getResponse()->getContent());
        self::assertSame('2026-10-02T14:30:00+00:00', (string) $urls['http://localhost/legal/mentions']->lastmod);
        self::assertSame('2026-10-02T14:30:00+00:00', (string) $urls['http://localhost/fr/legal/mentions']->lastmod);
    }

    public function testTheHomePageListsTheReleaseCovers(): void
    {
        $client = self::createClient();
        self::release('With cover', true);
        self::release('Without cover', false);

        $client->request('GET', '/sitemap.xml');

        $urls = self::urls($client->getResponse()->getContent());
        foreach (['http://localhost/', 'http://localhost/fr'] as $home) {
            $images = $urls[$home]->children(self::IMAGE)->image;
            self::assertCount(1, $images);
            self::assertMatchesRegularExpression('#^http://localhost/media/images/[0-9A-HJKMNP-TV-Z]{26}\.webp$#', (string) $images[0]?->children(self::IMAGE)->loc);
        }
        self::assertCount(0, $urls['http://localhost/contact']->children(self::IMAGE)->image);
    }

    /** @return array<string, \SimpleXMLElement> */
    private static function urls(string|false $xml): array
    {
        $urls = [];
        foreach (new \SimpleXMLElement((string) $xml)->url as $url) {
            $urls[(string) $url->loc] = $url;
        }

        return $urls;
    }

    private static function release(string $title, bool $cover): void
    {
        $id = Ulid::fromString(self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            ReleaseType::Single,
            new \DateTimeImmutable('2026-06-01'),
            new LocalizedText(),
            [],
            [],
            null,
            null,
        )))->id);
        if ($cover) {
            self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover($id, \dirname(__DIR__, 2).'/Fixtures/large-fern.png'));
        }
    }
}
