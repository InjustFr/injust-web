<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Music;

use App\Application\Music\FinderResult;
use App\Application\Music\PlatformFinder;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinks;
use App\Application\Music\RefreshReleaseLinks\RefreshReleaseLinksHandler;
use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\Exception\MissingUpc;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\Uid\Ulid;

final class RefreshReleaseLinksTest extends TestCase
{
    private BufferingLogger $logger;

    private int $commits = 0;

    protected function setUp(): void
    {
        $this->logger = new BufferingLogger();
    }

    public function testEachConfiguredFinderIsAskedAndItsAnswerApplied(): void
    {
        $release = self::release('701518909440');
        $finders = [
            self::finder(Platform::Spotify, FinderResult::found('https://open.spotify.com/album/x')),
            self::finder(Platform::Deezer, FinderResult::notFound()),
            self::finder(Platform::Tidal, FinderResult::failed()),
            self::finder(Platform::AppleMusic, null, configured: false),
        ];

        $report = $this->handler($release, $finders)(new RefreshReleaseLinks($release->id()));

        self::assertSame(['spotify' => 'found', 'deezer' => 'notFound', 'tidal' => 'failed', 'apple-music' => 'notConfigured'], $report->report);
        self::assertEquals(ReleaseView::of($release, new \DateTimeImmutable('2026-11-10 09:00:00 UTC')), $report->release);
        self::assertSame([['spotify', 'https://open.spotify.com/album/x', 'auto']], array_map(static fn (PlatformLink $link): array => [$link->platform->value, $link->url, $link->source->value], $release->links()));
        self::assertEquals(new \DateTimeImmutable('2026-11-10 09:00:00 UTC'), $release->linksCheckedAt());
        self::assertSame(1, $this->commits);
        self::assertSame([], $this->logger->cleanLogs());
    }

    public function testAFinderThatThrowsCountsAsFailedKeepsThePreviousLinkAndIsLoggedWithoutDetails(): void
    {
        $release = self::release('701518909440');
        $this->handler($release, [self::finder(Platform::Deezer, FinderResult::found('https://www.deezer.com/album/1'))])(new RefreshReleaseLinks($release->id()));

        $report = $this->handler($release, [self::finder(Platform::Deezer, new \RuntimeException('secret token abc'))])(new RefreshReleaseLinks($release->id()));

        self::assertSame(['deezer' => 'failed'], $report->report);
        self::assertSame('https://www.deezer.com/album/1', $release->linkTo(Platform::Deezer));
        $logs = $this->logger->cleanLogs();
        self::assertCount(1, $logs);
        self::assertSame(['warning', 'Platform lookup failed.', ['platform' => 'deezer', 'exception' => \RuntimeException::class]], $logs[0]);
    }

    public function testAFinderAnsweringAnUnsafeUrlCountsAsFailed(): void
    {
        $release = self::release('701518909440');

        $report = $this->handler($release, [self::finder(Platform::Deezer, 'http://www.deezer.com/album/1')])(new RefreshReleaseLinks($release->id()));

        self::assertSame(['deezer' => 'failed'], $report->report);
        self::assertSame([], $release->links());
    }

    public function testAReleaseWithoutUpcCannotBeRefreshed(): void
    {
        $release = self::release(null);

        try {
            $this->handler($release, [self::finder(Platform::Deezer, FinderResult::notFound())])(new RefreshReleaseLinks($release->id()));
            self::fail('A release without UPC was refreshed.');
        } catch (MissingUpc $refused) {
            self::assertSame('music.missing_upc', $refused->getMessage());
        }
        self::assertSame(0, $this->commits);
        self::assertNull($release->linksCheckedAt());
    }

    private static function release(?string $upc): Release
    {
        return Release::draft(new ReleaseDetails('White Men are the Issue', null, ReleaseType::Single, new \DateTimeImmutable('2026-06-01'), new LocalizedText(), [], [], null, null, $upc), 0);
    }

    private static function finder(Platform $platform, FinderResult|\Throwable|string|null $answer, bool $configured = true): PlatformFinder
    {
        return new readonly class($platform, $answer, $configured) implements PlatformFinder {
            public function __construct(private Platform $platform, private FinderResult|\Throwable|string|null $answer, private bool $configured)
            {
            }

            public function platform(): Platform
            {
                return $this->platform;
            }

            public function configured(): bool
            {
                return $this->configured;
            }

            public function find(string $upc): FinderResult
            {
                return match (true) {
                    '701518909440' !== $upc => throw new \LogicException('Wrong UPC '.$upc),
                    $this->answer instanceof FinderResult => $this->answer,
                    $this->answer instanceof \Throwable => throw $this->answer,
                    \is_string($this->answer) => FinderResult::found($this->answer),
                    default => throw new \LogicException('An unconfigured finder was asked.'),
                };
            }
        };
    }

    /** @param list<PlatformFinder> $finders */
    private function handler(Release $release, array $finders): RefreshReleaseLinksHandler
    {
        $releases = $this->createStub(ReleaseRepository::class);
        $releases->method('get')->willReturnCallback(static fn (Ulid $id): Release => $id->equals($release->id()) ? $release : throw new \LogicException('Unknown release'));
        $transaction = new class($this->commits) implements Transaction {
            public function __construct(private int &$commits)
            {
            }

            public function commit(): void
            {
                ++$this->commits;
            }
        };

        return new RefreshReleaseLinksHandler($releases, $finders, new MockClock('2026-11-10 09:00:00 UTC'), $this->logger, $transaction);
    }
}
