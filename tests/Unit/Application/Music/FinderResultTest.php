<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Music;

use App\Application\Music\FinderResult;
use App\Application\Music\LookupOutcome;
use App\Domain\Music\FoundLink;
use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;
use App\Domain\Shared\Exception\InvalidUrl;
use PHPUnit\Framework\TestCase;

final class FinderResultTest extends TestCase
{
    public function testAFoundAlbumBecomesALink(): void
    {
        $result = FinderResult::found('https://www.deezer.com/album/1');

        self::assertSame([LookupOutcome::Found, 'https://www.deezer.com/album/1'], [$result->outcome, $result->url]);
        self::assertEquals(new FoundLink(Platform::Deezer, 'https://www.deezer.com/album/1'), $result->link(Platform::Deezer));
    }

    public function testALinkFoundBySearchKeepsItsSource(): void
    {
        $result = FinderResult::found('https://www.youtube.com/playlist?list=x', LinkSource::Search);

        self::assertSame(LinkSource::Auto, FinderResult::found('https://www.deezer.com/album/1')->source);
        self::assertEquals(new FoundLink(Platform::Youtube, 'https://www.youtube.com/playlist?list=x', LinkSource::Search), $result->link(Platform::Youtube));
    }

    public function testAMissIsAnAnswerWithoutLink(): void
    {
        self::assertEquals(new FoundLink(Platform::Tidal, null), FinderResult::notFound()->link(Platform::Tidal));
        self::assertSame(LookupOutcome::NotFound, FinderResult::notFound()->outcome);
    }

    public function testAFailureOrAMissingKeyIsNoAnswer(): void
    {
        self::assertSame([LookupOutcome::Failed, null, null], [FinderResult::failed()->outcome, FinderResult::failed()->url, FinderResult::failed()->link(Platform::Spotify)]);
        self::assertSame([LookupOutcome::NotConfigured, null], [FinderResult::notConfigured()->outcome, FinderResult::notConfigured()->link(Platform::Spotify)]);
    }

    public function testAFoundUrlMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        FinderResult::found('http://www.deezer.com/album/1');
    }
}
