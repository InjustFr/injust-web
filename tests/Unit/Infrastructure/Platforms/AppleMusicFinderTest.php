<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Platforms;

use App\Application\Music\FinderResult;
use App\Domain\Music\Platform;
use App\Infrastructure\Platforms\AppleMusicFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class AppleMusicFinderTest extends TestCase
{
    use RecordedApi;

    private const string URL = 'GET https://itunes.apple.com/lookup?upc=701518909440&entity=album';

    public function testItNeedsNoKey(): void
    {
        $finder = new AppleMusicFinder($this->api([]));

        self::assertSame([Platform::AppleMusic, true], [$finder->platform(), $finder->configured()]);
    }

    public function testAStorefrontNeutralLinkIsBuiltFromTheCollectionId(): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => self::json(['resultCount' => 1, 'results' => [['wrapperType' => 'collection', 'collectionId' => 6804428384, 'collectionViewUrl' => 'https://music.apple.com/us/album/white-men-are-the-issue-single/6804428384?uo=4']]])]));

        self::assertEquals(FinderResult::found('https://music.apple.com/album/6804428384'), $finder->find('701518909440'));
        self::assertSame([self::URL], $this->requests);
    }

    public function testNoResultIsNotFound(): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => self::json(['resultCount' => 0, 'results' => []])]));

        self::assertEquals(FinderResult::notFound(), $finder->find('701518909440'));
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function oddAnswers(): iterable
    {
        yield 'empty' => [[]];
        yield 'error message' => [['errorMessage' => 'Invalid value(s) for key(s): [upc]']];
        yield 'results not a list' => [['results' => 'none']];
    }

    /** @param array<mixed> $answer */
    #[DataProvider('oddAnswers')]
    public function testAnAnswerWithoutResultsIsAFailureNotAMiss(array $answer): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => self::json($answer)]));

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testAResultWithoutNumericIdIsAFailure(): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => self::json(['resultCount' => 1, 'results' => [['collectionId' => '68/../x']]])]));

        $this->expectException(\UnexpectedValueException::class);

        $finder->find('701518909440');
    }

    public function testAnHttpErrorIsAFailure(): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => new MockResponse('', ['http_code' => 403])]));

        $this->expectException(ClientExceptionInterface::class);

        $finder->find('701518909440');
    }

    public function testATimeoutIsAFailure(): void
    {
        $finder = new AppleMusicFinder($this->api([self::URL => new MockResponse('', ['error' => 'Idle timeout reached'])]));

        $this->expectException(TransportExceptionInterface::class);

        $finder->find('701518909440');
    }
}
