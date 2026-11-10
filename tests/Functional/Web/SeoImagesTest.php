<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Application\Music\AddRelease\AddRelease;
use App\Application\Music\AddRelease\AddReleaseHandler;
use App\Application\Music\FeatureRelease\FeatureRelease;
use App\Application\Music\FeatureRelease\FeatureReleaseHandler;
use App\Application\Music\UploadPortrait\UploadPortrait;
use App\Application\Music\UploadPortrait\UploadPortraitHandler;
use App\Application\Music\UploadReleaseCover\UploadReleaseCover;
use App\Application\Music\UploadReleaseCover\UploadReleaseCoverHandler;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseType;
use App\Domain\Settings\SiteImage;
use App\Domain\Shared\LocalizedText;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Ulid;

final class SeoImagesTest extends WebTestCase
{
    use ConfiguresSite;

    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-11-10 09:00:00'));
    }

    public function testEveryImageKnowsItsSizeAndOnlyTheHeroPhotoIsFetchedFirst(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Hero);
        self::upload(SiteImage::Logo);
        self::getContainer()->get(UploadPortraitHandler::class)(new UploadPortrait(self::fern()));
        self::getContainer()->get(FeatureReleaseHandler::class)(new FeatureRelease(self::release('Album', '2026-09-04'), true));
        self::release('Single', '2026-06-01');

        $crawler = $client->request('GET', '/');

        $images = $crawler->filter('img');
        self::assertCount(6, $images);
        $images->each(static function (Crawler $image): void {
            $size = getimagesize((string) self::$kernel?->getShareDir().'/images/'.basename((string) $image->attr('src')));
            self::assertIsArray($size);
            self::assertSame([(string) $size[0], (string) $size[1]], [$image->attr('width'), $image->attr('height')], (string) $image->attr('src'));
        });
        $hero = $crawler->filter('img.hero-photo');
        self::assertSame('high', $hero->attr('fetchpriority'));
        self::assertNull($hero->attr('loading'));
        self::assertSame(
            [$hero->attr('src'), 'image', 'high'],
            [$crawler->filter('link[rel="preload"][as="image"]')->attr('href'), 'image', $crawler->filter('link[rel="preload"][as="image"]')->attr('fetchpriority')],
        );
        foreach (['.release img', '.portrait'] as $selector) {
            $crawler->filter($selector)->each(static function (Crawler $image) use ($selector): void {
                self::assertSame(['lazy', 'async'], [$image->attr('loading'), $image->attr('decoding')], $selector);
            });
        }
        foreach (['.wordmark img', '.hero-release-cover'] as $selector) {
            self::assertSame([null, 'async'], [$crawler->filter($selector)->attr('loading'), $crawler->filter($selector)->attr('decoding')], $selector);
        }
    }

    public function testAnImageWhoseFileIsGoneStillRendersWithoutASize(): void
    {
        $client = self::createClient();
        self::upload(SiteImage::Logo);
        foreach (glob((string) self::$kernel?->getShareDir().'/images/*.webp') ?: [] as $file) {
            unlink($file);
        }

        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertNull($crawler->filter('.wordmark img')->attr('width'));
    }

    private static function release(string $title, string $date): Ulid
    {
        $id = Ulid::fromString(self::getContainer()->get(AddReleaseHandler::class)(new AddRelease(new ReleaseDetails(
            $title,
            null,
            ReleaseType::Single,
            new \DateTimeImmutable($date),
            new LocalizedText(),
            [],
            [],
            null,
            null,
        )))->id);
        self::getContainer()->get(UploadReleaseCoverHandler::class)(new UploadReleaseCover($id, self::fern()));

        return $id;
    }

    private static function fern(): string
    {
        return \dirname(__DIR__, 2).'/Fixtures/large-fern.png';
    }
}
