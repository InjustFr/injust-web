<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Site;

use App\Application\Music\ReleaseView;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Music\Track;
use App\Domain\Shared\LocalizedText;
use App\Presentation\Web\Site\MusicStructuredData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class MusicStructuredDataTest extends TestCase
{
    public function testAReleaseBecomesAMusicAlbumWithItsTracksAndLinks(): void
    {
        $release = Release::draft(new ReleaseDetails(
            'No Gods',
            null,
            ReleaseType::Album,
            new \DateTimeImmutable('2026-12-01'),
            new LocalizedText(),
            [new Track(1, 'Intro', 61), new Track(2, 'Riot', null), new Track(3, 'Outro', 600), new Track(4, 'Coda', 59)],
            [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com'), new PlatformLink(Platform::Deezer, 'https://deezer.com/album/1')],
            null,
            null,
        ), 0);

        self::assertSame([[
            '@type' => 'MusicAlbum',
            '@id' => 'https://injust.test/#release-no-gods',
            'name' => 'No Gods',
            'url' => 'https://injust.test/#release-no-gods',
            'albumReleaseType' => 'https://schema.org/AlbumRelease',
            'byArtist' => ['@id' => 'https://injust.test/#band'],
            'datePublished' => '2026-12-01',
            'numTracks' => 4,
            'track' => [
                ['@type' => 'MusicRecording', 'name' => 'Intro', 'position' => 1, 'duration' => 'PT1M1S'],
                ['@type' => 'MusicRecording', 'name' => 'Riot', 'position' => 2],
                ['@type' => 'MusicRecording', 'name' => 'Outro', 'position' => 3, 'duration' => 'PT10M0S'],
                ['@type' => 'MusicRecording', 'name' => 'Coda', 'position' => 4, 'duration' => 'PT0M59S'],
            ],
            'sameAs' => ['https://injust.bandcamp.com', 'https://deezer.com/album/1'],
        ]], self::structuredData()->releases([self::view($release)]));
    }

    public function testACoverBecomesTheAbsoluteImageUrl(): void
    {
        $release = self::release(ReleaseType::Single);
        $release->useCover('01JCOVER');

        $node = self::structuredData()->releases([self::view($release)])[0];

        self::assertSame('https://injust.test/media/images/01JCOVER.webp', $node['image'] ?? null);
    }

    public function testNoCoverMeansNoImage(): void
    {
        self::assertArrayNotHasKey('image', self::structuredData()->releases([self::view(self::release(ReleaseType::Single))])[0]);
    }

    #[DataProvider('types')]
    public function testEachTypeMapsToItsSchemaReleaseType(ReleaseType $type, string $schema): void
    {
        self::assertSame('https://schema.org/'.$schema, self::structuredData()->releases([self::view(self::release($type))])[0]['albumReleaseType']);
    }

    /** @return iterable<array{ReleaseType, string}> */
    public static function types(): iterable
    {
        yield [ReleaseType::Single, 'SingleRelease'];
        yield [ReleaseType::Ep, 'EPRelease'];
        yield [ReleaseType::Album, 'AlbumRelease'];
    }

    public function testEveryReleaseIsListedInOrder(): void
    {
        $nodes = self::structuredData()->releases([self::view(self::release(ReleaseType::Single, 'One')), self::view(self::release(ReleaseType::Ep, 'Two'))]);

        self::assertSame(['One', 'Two'], array_column($nodes, 'name'));
    }

    private static function release(ReleaseType $type, string $title = 'Riot'): Release
    {
        return Release::draft(new ReleaseDetails($title, null, $type, new \DateTimeImmutable('2026-01-01'), new LocalizedText(), [], [], null, null), 0);
    }

    private static function view(Release $release): ReleaseView
    {
        return ReleaseView::of($release, new \DateTimeImmutable('2026-06-01'));
    }

    private static function structuredData(): MusicStructuredData
    {
        $routes = new RouteCollection();
        $routes->add('home', new Route('/'));
        $routes->add('media_image', new Route('/media/images/{file}'));

        return new MusicStructuredData(new UrlGenerator($routes, new RequestContext('', 'GET', 'injust.test', 'https')));
    }
}
