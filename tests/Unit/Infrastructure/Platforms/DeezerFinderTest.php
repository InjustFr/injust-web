<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Domain\Music\Platform;
use App\Infrastructure\Platforms\DeezerFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class DeezerFinderTest extends TestCase
{
    use RecordedApi;

    private const string URL = 'GET https://api.deezer.com/album/upc:701518909440';

    public function testItNeedsNoKey(): void
    {
        $finder = new DeezerFinder($this->api([]));

        self::assertSame([Platform::Deezer, true], [$finder->platform(), $finder->configured()]);
    }

    public function testTheAlbumLinkIsFound(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => self::json(['id' => 1061026552, 'title' => 'White Men are the Issue', 'link' => 'https://www.deezer.com/album/1061026552', 'cover_xl' => 'https://cdn-images.dzcdn.net/x.jpg', 'release_date' => '2026-06-01'])]));

        self::assertEquals(FinderResult::found('https://www.deezer.com/album/1061026552'), $finder->find('701518909440'));
        self::assertSame([self::URL], $this->requests);
    }

    public function testAnUnknownUpcIsNotFound(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => self::json(['error' => ['type' => 'DataException', 'message' => 'no data', 'code' => 800]])]));

        self::assertEquals(FinderResult::notFound(), $finder->find('701518909440'));
    }

    public function testAnyOtherErrorIsAFailure(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => self::json(['error' => ['type' => 'Exception', 'message' => 'Quota limit exceeded', 'code' => 4]])]));

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testTheLinkIsBuiltFromTheAlbumIdWhateverLinkDeezerAnswers(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => self::json(['id' => 1061026552, 'link' => 'https://evil.example/album/1061026552'])]));

        self::assertEquals(FinderResult::found('https://www.deezer.com/album/1061026552'), $finder->find('701518909440'));
    }

    /** @return iterable<string, array{mixed}> */
    public static function unusableIds(): iterable
    {
        yield 'missing' => [null];
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'string' => ['1061026552'];
    }

    #[DataProvider('unusableIds')]
    public function testAnAnswerWithoutAUsableIdIsAFailure(mixed $id): void
    {
        $finder = new DeezerFinder($this->api([self::URL => self::json(['id' => $id, 'link' => 'https://www.deezer.com/album/1'])]));

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testAnHttpErrorIsAFailure(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => new MockResponse('oops', ['http_code' => 503])]));

        $this->expectException(ServerExceptionInterface::class);

        $finder->find('701518909440');
    }

    public function testATimeoutIsAFailure(): void
    {
        $finder = new DeezerFinder($this->api([self::URL => new MockResponse('', ['error' => 'Idle timeout reached'])]));

        $this->expectException(TransportExceptionInterface::class);

        $finder->find('701518909440');
    }
}
