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
use App\Infrastructure\Platforms\YoutubePlaylists;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class YoutubeFinderTest extends TestCase
{
    use RecordedApi;

    private const string SEARCH = 'GET https://www.googleapis.com/youtube/v3/search';
    private const string ID = 'OLAK5uy_nbWdzQYfxDeG7ADva9IU5qzaNv-W_ZloE';
    private const string OTHER_ID = 'OLAK5uy_kAnotherPlaylistOfTheBand0000';

    private static function lookup(string $title = 'White Men are the Issue', string $upc = '701518909440'): ReleaseLookup
    {
        return new ReleaseLookup($upc, $title, ReleaseType::Single);
    }

    /** @return array<string, mixed> */
    private static function item(string $title = 'Album - White Men are the Issue', string $channel = 'Injust - Topic', mixed $id = self::ID): array
    {
        return ['kind' => 'youtube#searchResult', 'id' => ['kind' => 'youtube#playlist', 'playlistId' => $id], 'snippet' => ['title' => $title, 'channelTitle' => $channel, 'channelId' => 'UCInjustTopic']];
    }

    /** @param list<mixed> $items */
    private static function answer(array $items): MockResponse
    {
        return self::json(['kind' => 'youtube#searchListResponse', 'items' => $items]);
    }

    public function testItIsConfiguredByTheApiKey(): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([]), 'fake-key', 'Injust'), Platform::YoutubeMusic);
        $unset = new YoutubeFinder(new YoutubePlaylists($this->api([]), '', 'Injust'), Platform::Youtube);

        self::assertSame([Platform::YoutubeMusic, true], [$finder->platform(), $finder->configured()]);
        self::assertSame([Platform::Youtube, false], [$unset->platform(), $unset->configured()]);
    }

    public function testOneSearchAnswersYoutubeMusicAndYoutube(): void
    {
        $playlists = new YoutubePlaylists($this->api([self::SEARCH => self::answer([self::item()])]), 'fake-key', 'Injust');
        $music = new YoutubeFinder($playlists, Platform::YoutubeMusic);
        $video = new YoutubeFinder($playlists, Platform::Youtube);
        $refresh = self::lookup();

        self::assertEquals(FinderResult::found('https://music.youtube.com/playlist?list='.self::ID, LinkSource::Search), $music->find($refresh));
        self::assertEquals(FinderResult::found('https://www.youtube.com/playlist?list='.self::ID, LinkSource::Search), $video->find($refresh));
        self::assertCount(1, $this->requests);
        parse_str((string) parse_url($this->requests[0], \PHP_URL_QUERY), $query);
        self::assertSame(['part' => 'snippet', 'type' => 'playlist', 'maxResults' => '10', 'q' => 'Injust White Men are the Issue'], $query);
        self::assertSame(['X-Goog-Api-Key: fake-key'], $this->apiKeys);
    }

    public function testEachRefreshSearchesAgain(): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([self::SEARCH => self::answer([])]), 'fake-key', 'Injust'), Platform::Youtube);
        $refresh = self::lookup();

        $finder->find($refresh);
        $finder->find($refresh);
        $finder->find(self::lookup());

        self::assertCount(2, $this->requests);
    }

    /** @return iterable<string, array{list<mixed>, ?string}> */
    public static function searchAnswers(): iterable
    {
        yield 'album prefix on the topic channel' => [[self::item()], self::ID];
        yield 'exact title on the artist channel' => [[self::item('White Men are the Issue', 'Injust')], self::ID];
        yield 'case and punctuation' => [[self::item('WHITE MEN, are the issue!', 'INJUST - topic')], self::ID];
        yield 'single prefix' => [[self::item('Single - White Men are the Issue')], self::ID];
        yield 'ep prefix' => [[self::item('EP - White Men are the Issue')], self::ID];
        yield 'single suffix' => [[self::item('White Men are the Issue - Single')], self::ID];
        yield 'ep suffix' => [[self::item('White Men are the Issue - EP')], self::ID];
        yield 'spaces around a prefix' => [[self::item('  Album - White Men are the Issue')], self::ID];
        yield 'spaces around a suffix' => [[self::item('White Men are the Issue - Single  ')], self::ID];
        yield 'first match after decoys' => [[self::item(channel: 'Some Fan'), self::item('White Men are the Issue (Live)'), self::item(id: self::OTHER_ID)], self::OTHER_ID];
        yield 'a homonym topic channel is not told apart' => [[['id' => ['playlistId' => self::ID], 'snippet' => ['title' => 'White Men are the Issue', 'channelTitle' => 'Injust - Topic', 'channelId' => 'UCAnotherInjust']]], self::ID];
        yield 'another channel' => [[self::item(channel: 'Injust Fans')], null];
        yield 'a topic channel of another artist' => [[self::item(channel: 'Other - Topic')], null];
        yield 'another title' => [[self::item('White Men are the Issue (Live)')], null];
        yield 'a prefix in the middle is kept' => [[self::item('White Men - Single - are the Issue')], null];
        yield 'not an album playlist' => [[self::item(id: 'PLx0sYbCqOb8TBPRdmBHs5Iftvv9TPboYG')], null];
        yield 'an id too short' => [[self::item(id: 'OLAK5uy_short')], null];
        yield 'an id with a forbidden character' => [[self::item(id: 'OLAK5uy_nbWdzQYfxDeG7ADva9IU5qz/v-W_ZloE')], null];
        yield 'an id that is not a string' => [[self::item(id: 42)], null];
        yield 'an item without snippet' => [[['id' => ['playlistId' => self::ID]]], null];
        yield 'an item that is not an object' => [['oops'], null];
        yield 'no item' => [[], null];
    }

    /** @param list<mixed> $items */
    #[DataProvider('searchAnswers')]
    public function testOnlyTheAutoGeneratedAlbumOfTheBandWithTheSameTitleMatches(array $items, ?string $id): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([self::SEARCH => self::answer($items)]), 'fake-key', 'Injust'), Platform::Youtube);

        self::assertEquals(null === $id ? FinderResult::notFound() : FinderResult::found('https://www.youtube.com/playlist?list='.$id, LinkSource::Search), $finder->find(self::lookup()));
    }

    public function testTheReleaseTitleIsNormalisedToo(): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([self::SEARCH => self::answer([self::item('White Men are the Issue')])]), 'fake-key', 'Injust'), Platform::YoutubeMusic);

        self::assertSame(LinkSource::Search, $finder->find(self::lookup('Single - White men are the issue.'))->source);
    }

    public function testCaseIsFoldedBeyondAscii(): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([self::SEARCH => self::answer([self::item('Été', 'ÉMEUTE - TOPIC')])]), 'fake-key', 'Émeute'), Platform::Youtube);

        self::assertSame(LookupOutcome::Found, $finder->find(self::lookup('ÉTÉ'))->outcome);
    }

    public function testAQuotaErrorIsAFailureForBothPlatformsWithASingleCall(): void
    {
        $playlists = new YoutubePlaylists($this->api([self::SEARCH => self::json(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]), 'fake-key', 'Injust');
        $refresh = self::lookup();

        foreach ([Platform::YoutubeMusic, Platform::Youtube] as $platform) {
            try {
                new YoutubeFinder($playlists, $platform)->find($refresh);
                self::fail('A quota error was not a failure.');
            } catch (ClientExceptionInterface $failure) {
                self::assertSame(403, $failure->getResponse()->getStatusCode());
            }
        }
        self::assertCount(1, $this->requests);
    }

    public function testAnAnswerWithoutItemsIsAFailure(): void
    {
        $finder = new YoutubeFinder(new YoutubePlaylists($this->api([self::SEARCH => self::json(['kind' => 'youtube#searchListResponse'])]), 'fake-key', 'Injust'), Platform::Youtube);

        $this->expectException(\UnexpectedValueException::class);

        $finder->find(self::lookup());
    }
}
