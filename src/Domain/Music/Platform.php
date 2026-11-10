<?php

declare(strict_types=1);

namespace App\Domain\Music;

enum Platform: string
{
    case Spotify = 'spotify';
    case AppleMusic = 'apple-music';
    case Deezer = 'deezer';
    case YoutubeMusic = 'youtube-music';
    case Youtube = 'youtube';
    case Bandcamp = 'bandcamp';
    case Tidal = 'tidal';
    case AmazonMusic = 'amazon-music';
    case Soundcloud = 'soundcloud';
    case Other = 'other';
}
