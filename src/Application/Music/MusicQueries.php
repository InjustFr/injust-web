<?php

declare(strict_types=1);

namespace App\Application\Music;

interface MusicQueries
{
    public function home(\DateTimeImmutable $now): HomeView;

    /** @return list<ReleaseView> */
    public function releases(\DateTimeImmutable $now): array;

    /** @return list<VideoView> */
    public function videos(): array;

    /** @return list<SocialLinkView> */
    public function socialLinks(): array;

    public function text(): MusicTextView;
}
