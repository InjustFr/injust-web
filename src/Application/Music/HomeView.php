<?php

declare(strict_types=1);

namespace App\Application\Music;

final readonly class HomeView
{
    public ?ReleaseView $featured;

    /**
     * @param list<ReleaseView>    $releases
     * @param list<VideoView>      $videos
     * @param list<SocialLinkView> $socialLinks
     */
    public function __construct(
        public array $releases,
        public array $videos,
        public MusicTextView $text,
        public array $socialLinks,
    ) {
        $featured = array_filter($releases, static fn (ReleaseView $release): bool => $release->featured);
        $this->featured = [] === $featured ? null : reset($featured);
    }
}
