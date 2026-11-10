<?php

declare(strict_types=1);

namespace App\Domain\Music;

enum SocialNetwork: string
{
    case Instagram = 'instagram';
    case Tiktok = 'tiktok';
    case Youtube = 'youtube';
    case Spotify = 'spotify';
    case Facebook = 'facebook';
    case Bandcamp = 'bandcamp';
    case Soundcloud = 'soundcloud';
    case Linktree = 'linktree';
    case X = 'x';
    case Threads = 'threads';
    case Other = 'other';
}
