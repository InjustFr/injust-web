<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Application\Music\LookupOutcome;
use App\Application\Music\ReleaseLookup;
use App\Domain\Music\LinkSource;
use App\Domain\Music\Platform;
use App\Domain\Music\ReleaseType;
use App\Infrastructure\Platforms\YoutubeFinder;
use App\Infrastructure\Platforms\YoutubeTracks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class YoutubeFinderTest extends TestCase
{
    use RecordedApi;

    private const string SEARCH = 'GET https://www.googleapis.com/youtube/v3/search';
    private const string VIDEOS = 'GET https://www.googleapis.com/youtube/v3/videos';
    private const string ID = '7Q5mn7mihG8';
    private const string OTHER_ID = 'kAnotherTrk';

    private static function lookup(string $title = 'White Men are the Issue', string $upc = '701518909440'): ReleaseLookup
    {
        return new ReleaseLookup($upc, $title, ReleaseType::Single);
    }

    /** @return array<string, mixed> */
    private static function item(string $title = 'White Men are the Issue', string $channel = 'Injust - Topic', mixed $id = self::ID): array
    {
        return ['kind' => 'youtube#searchResult', 'id' => ['kind' => 'youtube#video', 'videoId' => $id], 'snippet' => ['title' => $title, 'channelTitle' => $channel, 'channelId' => 'UCInjustTopic']];
    }

    /** @param list<mixed> $items */
    private static function answer(array $items): MockResponse
    {
        return self::json(['kind' => 'youtube#searchListResponse', 'items' => $items]);
    }

    public function testItIsConfiguredByTheApiKey(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([]), 'fake-key', 'Injust'), Platform::YoutubeMusic);
        $unset = new YoutubeFinder(new YoutubeTracks($this->api([]), '', 'Injust'), Platform::Youtube);

        self::assertSame([Platform::YoutubeMusic, true], [$finder->platform(), $finder->configured()]);
        self::assertSame([Platform::Youtube, false], [$unset->platform(), $unset->configured()]);
    }

    public function testOneSearchAnswersYoutubeMusicAndYoutube(): void
    {
        $tracks = new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item()])]), 'fake-key', 'Injust');
        $music = new YoutubeFinder($tracks, Platform::YoutubeMusic);
        $video = new YoutubeFinder($tracks, Platform::Youtube);
        $refresh = self::lookup();

        self::assertEquals(FinderResult::found('https://music.youtube.com/watch?v='.self::ID, LinkSource::Search), $music->find($refresh));
        self::assertEquals(FinderResult::found('https://www.youtube.com/watch?v='.self::ID, LinkSource::Search), $video->find($refresh));
        self::assertCount(1, $this->requests);
        parse_str((string) parse_url($this->requests[0], \PHP_URL_QUERY), $query);
        self::assertSame(['part' => 'snippet', 'type' => 'video', 'maxResults' => '10', 'q' => 'Injust White Men are the Issue'], $query);
        self::assertSame(['X-Goog-Api-Key: fake-key'], $this->apiKeys);
    }

    public function testEachRefreshSearchesAgain(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([])]), 'fake-key', 'Injust'), Platform::Youtube);
        $refresh = self::lookup();

        $finder->find($refresh);
        $finder->find($refresh);
        $finder->find(self::lookup());

        self::assertCount(2, $this->requests);
    }

    /** @return iterable<string, array{list<mixed>, ?string}> */
    public static function searchAnswers(): iterable
    {
        yield 'the track on the topic channel' => [[self::item()], self::ID];
        yield 'case and punctuation' => [[self::item('WHITE MEN, are the issue!', 'INJUST - topic')], self::ID];
        yield 'first match after decoys' => [[self::item(channel: 'Some Fan'), self::item('White Men are the Issue (Live)'), self::item(id: self::OTHER_ID)], self::OTHER_ID];
        yield 'a homonym topic channel is not told apart' => [[['id' => ['videoId' => self::ID], 'snippet' => ['title' => 'White Men are the Issue', 'channelTitle' => 'Injust - Topic', 'channelId' => 'UCAnotherInjust']]], self::ID];
        yield 'a video of the artist channel' => [[self::item(channel: 'Injust')], null];
        yield 'another channel' => [[self::item(channel: 'Injust Fans')], null];
        yield 'a topic channel of another artist' => [[self::item(channel: 'Other - Topic')], null];
        yield 'another title' => [[self::item('White Men are the Issue (Live)')], null];
        yield 'an id too short' => [[self::item(id: '7Q5mn7mih')], null];
        yield 'an id too long' => [[self::item(id: '7Q5mn7mihG8x')], null];
        yield 'an id with a forbidden character' => [[self::item(id: '7Q5mn/mihG8')], null];
        yield 'an id that is not a string' => [[self::item(id: 42)], null];
        yield 'an item without snippet' => [[['id' => ['videoId' => self::ID]]], null];
        yield 'an item that is not an object' => [['oops'], null];
        yield 'no item' => [[], null];
    }

    /** @param list<mixed> $items */
    #[DataProvider('searchAnswers')]
    public function testOnlyTheTrackOfTheBandTopicChannelWithTheSameTitleMatches(array $items, ?string $id): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer($items), self::VIDEOS => self::videos([])]), 'fake-key', 'Injust'), Platform::Youtube);

        self::assertEquals(null === $id ? FinderResult::notFound() : FinderResult::found('https://www.youtube.com/watch?v='.$id, LinkSource::Search), $finder->find(self::lookup()));
    }

    public function testTheReleaseTitleIsNormalisedToo(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item('White Men are the Issue')])]), 'fake-key', 'Injust'), Platform::YoutubeMusic);

        self::assertSame(LinkSource::Search, $finder->find(self::lookup('white men are the issue.'))->source);
    }

    /** @param array<string, string> $descriptions */
    private static function videos(array $descriptions): MockResponse
    {
        return self::json(['kind' => 'youtube#videoListResponse', 'items' => array_map(static fn (string $id, string $description): array => ['id' => $id, 'snippet' => ['description' => $description]], array_keys($descriptions), $descriptions)]);
    }

    private static function description(string $track, string $album): string
    {
        return "Provided to YouTube by DistroKid\n\n{$track} · Injust\n\n{$album}\n\n℗ 2026 Injust\n\nReleased on: 2026-12-04\n\nAuto-generated by YouTube.";
    }

    public function testAnAlbumIsFoundThroughTheAlbumLineOfOneOfItsTracks(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([
            self::SEARCH => self::answer([self::item('Riot Song', 'Some Fan', 'fAnVideo001'), self::item('Opening Track', id: 'tRackOne001'), self::item('Second Track', id: 'tRackTwo001')]),
            self::VIDEOS => self::videos(['tRackOne001' => self::description('Opening Track', 'Other Record'), 'tRackTwo001' => self::description('Second Track', 'We are F***ed')]),
        ]), 'fake-key', 'Injust'), Platform::YoutubeMusic);

        self::assertEquals(FinderResult::found('https://music.youtube.com/watch?v=tRackTwo001', LinkSource::Search), $finder->find(new ReleaseLookup('701518909440', 'We are f***ed', ReleaseType::Album)));
        self::assertCount(2, $this->requests);
        parse_str((string) parse_url($this->requests[1], \PHP_URL_QUERY), $query);
        self::assertSame(['part' => 'snippet', 'id' => 'tRackOne001,tRackTwo001'], $query);
        self::assertSame('X-Goog-Api-Key: fake-key', $this->apiKeys[1]);
    }

    public function testATrackWithTheReleaseTitleNeedsNoSecondCall(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item()])]), 'fake-key', 'Injust'), Platform::Youtube);

        $finder->find(self::lookup());

        self::assertCount(1, $this->requests);
    }

    public function testWithoutATopicTrackThereIsNoSecondCall(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item(channel: 'Injust')])]), 'fake-key', 'Injust'), Platform::Youtube);

        self::assertEquals(FinderResult::notFound(), $finder->find(self::lookup('Another Record')));
        self::assertCount(1, $this->requests);
    }

    /** @return iterable<string, array{mixed}> */
    public static function descriptionsWithoutTheAlbum(): iterable
    {
        yield 'another album' => [self::description('Opening Track', 'Other Record')];
        yield 'the album name on the track line' => ["Provided to YouTube by DistroKid\n\nAnother Record · Injust\n\nOther Record"];
        yield 'no track line' => ["Provided to YouTube by DistroKid\n\nAnother Record"];
        yield 'nothing after the track line' => ["Provided to YouTube by DistroKid\n\nOpening Track · Injust"];
        yield 'not a string' => [42];
    }

    #[DataProvider('descriptionsWithoutTheAlbum')]
    public function testATrackOfAnotherAlbumIsNotFound(mixed $description): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([
            self::SEARCH => self::answer([self::item('Opening Track', id: 'tRackOne001')]),
            self::VIDEOS => self::json(['items' => [['id' => 'tRackOne001', 'snippet' => ['description' => $description]]]]),
        ]), 'fake-key', 'Injust'), Platform::Youtube);

        self::assertEquals(FinderResult::notFound(), $finder->find(new ReleaseLookup('701518909440', 'Another Record', ReleaseType::Album)));
    }

    public function testHtmlEntitiesInTheAnswerAreDecoded(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item('Don&#39;t tell me &quot;who&quot; to be &amp; why')])]), 'fake-key', 'Injust'), Platform::Youtube);

        self::assertSame(LookupOutcome::Found, $finder->find(self::lookup('Don\'t tell me "who" to be & why'))->outcome);
    }

    public function testCaseIsFoldedBeyondAscii(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::answer([self::item('Été', 'ÉMEUTE - TOPIC')])]), 'fake-key', 'Émeute'), Platform::Youtube);

        self::assertSame(LookupOutcome::Found, $finder->find(self::lookup('ÉTÉ'))->outcome);
    }

    public function testAQuotaErrorIsAFailureForBothPlatformsWithASingleCall(): void
    {
        $tracks = new YoutubeTracks($this->api([self::SEARCH => self::json(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]), 'fake-key', 'Injust');
        $refresh = self::lookup();

        foreach ([Platform::YoutubeMusic, Platform::Youtube] as $platform) {
            try {
                new YoutubeFinder($tracks, $platform)->find($refresh);
                self::fail('A quota error was not a failure.');
            } catch (ClientExceptionInterface $failure) {
                self::assertSame(403, $failure->getResponse()->getStatusCode());
            }
        }
        self::assertCount(1, $this->requests);
    }

    public function testAnAnswerWithoutItemsIsAFailure(): void
    {
        $finder = new YoutubeFinder(new YoutubeTracks($this->api([self::SEARCH => self::json(['kind' => 'youtube#searchListResponse'])]), 'fake-key', 'Injust'), Platform::Youtube);

        $this->expectException(\UnexpectedValueException::class);

        $finder->find(self::lookup());
    }
}
