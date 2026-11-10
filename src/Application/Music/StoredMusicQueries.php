<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\MusicTextRepository;
use App\Domain\Music\Platform;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Music\SocialLinkRepository;
use App\Domain\Music\VideoRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class StoredMusicQueries implements MusicQueries
{
    /** @param iterable<PlatformFinder> $finders */
    public function __construct(
        private ReleaseRepository $releases,
        private VideoRepository $videos,
        private SocialLinkRepository $socialLinks,
        private MusicTextRepository $texts,
        #[AutowireIterator(PlatformFinder::class)]
        private iterable $finders,
    ) {
    }

    public function home(\DateTimeImmutable $now): HomeView
    {
        return new HomeView($this->releases($now), $this->videos(), $this->text(), $this->socialLinks());
    }

    public function releases(\DateTimeImmutable $now): array
    {
        return array_map(static fn (Release $release): ReleaseView => ReleaseView::of($release, $now), $this->releases->all());
    }

    public function videos(): array
    {
        return array_map(VideoView::of(...), $this->videos->all());
    }

    public function socialLinks(): array
    {
        return array_map(SocialLinkView::of(...), $this->socialLinks->all());
    }

    public function text(): MusicTextView
    {
        return MusicTextView::of($this->texts->current());
    }

    public function listen(string $slug, \DateTimeImmutable $now): ListenView
    {
        return ListenView::of($this->releases->bySlug($slug), $now);
    }

    public function dueForLinkRefresh(\DateTimeImmutable $now): array
    {
        $findable = array_map(static fn (PlatformFinder $finder): Platform => $finder->platform(), array_filter(iterator_to_array($this->finders, false), static fn (PlatformFinder $finder): bool => $finder->configured()));
        $due = array_filter($this->releases->all(), static fn (Release $release): bool => $release->linksDue($now, array_values($findable)));

        return array_values(array_map(static fn (Release $release): string => $release->id()->toBase32(), $due));
    }

    public function finders(): array
    {
        return array_map(FinderView::of(...), iterator_to_array($this->finders, false));
    }
}
