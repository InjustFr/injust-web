<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Music;

use App\Application\Music\CountReleaseLinkClick\CountReleaseLinkClick;
use App\Application\Music\CountReleaseLinkClick\CountReleaseLinkClickHandler;
use App\Application\Music\HideReleasePlatforms\HideReleasePlatforms;
use App\Application\Music\HideReleasePlatforms\HideReleasePlatformsHandler;
use App\Application\Music\ReleaseView;
use App\Application\Transaction;
use App\Domain\Music\FoundLink;
use App\Domain\Music\Platform;
use App\Domain\Music\PlatformLink;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseDetails;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Music\ReleaseType;
use App\Domain\Shared\Exception\NotFound;
use App\Domain\Shared\LocalizedText;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class ReleaseLinkHandlersTest extends TestCase
{
    private int $commits = 0;

    /** @var list<array{string, string}> */
    private array $clicks = [];

    private Release $release;

    protected function setUp(): void
    {
        $this->release = Release::draft(new ReleaseDetails('White Men are the Issue', null, ReleaseType::Single, new \DateTimeImmutable('2026-06-01'), new LocalizedText(), [], [new PlatformLink(Platform::Bandcamp, 'https://injust.bandcamp.com/track/x')], null, null, '701518909440'), 0);
        $this->release->applyFoundLinks([new FoundLink(Platform::Deezer, 'https://www.deezer.com/album/1'), new FoundLink(Platform::Spotify, 'https://open.spotify.com/album/1')], new \DateTimeImmutable('2026-11-01'));
    }

    public function testAClickOnAVisibleLinkIsCountedCommittedAndLeadsToItsUrl(): void
    {
        $url = new CountReleaseLinkClickHandler($this->releases(), $this->transaction())(new CountReleaseLinkClick('white-men-are-the-issue', Platform::Deezer));

        self::assertSame('https://www.deezer.com/album/1', $url);
        self::assertSame([[$this->release->id()->toBase32(), 'deezer']], $this->clicks);
        self::assertSame(1, $this->commits);
    }

    public function testAClickOnAPlatformWithoutLinkIsNotFoundAndNotCounted(): void
    {
        try {
            new CountReleaseLinkClickHandler($this->releases(), $this->transaction())(new CountReleaseLinkClick('white-men-are-the-issue', Platform::Tidal));
            self::fail('A click without link was counted.');
        } catch (NotFound $missing) {
            self::assertSame(['subject' => 'release_link', 'id' => 'tidal'], $missing->parameters());
        }
        self::assertSame([[], 0], [$this->clicks, $this->commits]);
    }

    public function testHiddenPlatformsLoseTheirAutoLinkAndAreCommitted(): void
    {
        $view = new HideReleasePlatformsHandler($this->releases(), new MockClock('2026-11-10 09:00:00 UTC'), $this->transaction())(new HideReleasePlatforms($this->release->id(), [Platform::Deezer, Platform::Bandcamp]));

        self::assertEquals(ReleaseView::of($this->release, new \DateTimeImmutable('2026-11-10 09:00:00 UTC')), $view);
        self::assertSame([Platform::Deezer, Platform::Bandcamp], $view->hiddenPlatforms);
        self::assertSame(['bandcamp', 'spotify'], array_map(static fn (PlatformLink $link): string => $link->platform->value, $view->links));
        self::assertSame(1, $this->commits);
    }

    private function releases(): ReleaseRepository
    {
        $release = $this->release;
        $releases = $this->createStub(ReleaseRepository::class);
        $releases->method('get')->willReturnCallback(static fn (Ulid $id): Release => $id->equals($release->id()) ? $release : throw new NotFound('release', (string) $id));
        $releases->method('bySlug')->willReturnCallback(static fn (string $slug): Release => $release->slug() === $slug ? $release : throw new NotFound('release', $slug));
        $releases->method('countClick')->willReturnCallback(function (Ulid $id, Platform $platform): void {
            $this->clicks[] = [$id->toBase32(), $platform->value];
        });

        return $releases;
    }

    private function transaction(): Transaction
    {
        return new class($this->commits) implements Transaction {
            public function __construct(private int &$commits)
            {
            }

            public function commit(): void
            {
                ++$this->commits;
            }
        };
    }
}
