<?php

declare(strict_types=1);

namespace App\Application\Music;

use App\Domain\Music\MusicTextRepository;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Music\SocialLinkRepository;
use App\Domain\Music\VideoRepository;

final readonly class StoredMusicQueries implements MusicQueries
{
    public function __construct(
        private ReleaseRepository $releases,
        private VideoRepository $videos,
        private SocialLinkRepository $socialLinks,
        private MusicTextRepository $texts,
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
}
