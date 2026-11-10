<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Video;
use App\Domain\Music\YoutubeId;
use PHPUnit\Framework\TestCase;

final class VideoTest extends TestCase
{
    public function testAVideoKeepsItsIdTitleThumbnailAndPosition(): void
    {
        $video = Video::add(YoutubeId::parse('https://youtu.be/dQw4w9WgXcQ'), ' Riot (live) ', 'THUMB', 2);

        self::assertSame(['dQw4w9WgXcQ', 'Riot (live)', 'THUMB', 2], [$video->youtubeId()->value, $video->title(), $video->thumbnail(), $video->position()]);
    }

    public function testItIsRenamedAndMoved(): void
    {
        $video = Video::add(YoutubeId::parse('dQw4w9WgXcQ'), 'Riot', null, 0);

        $video->rename(' Riot (official) ');
        $video->moveTo(5);

        self::assertSame(['Riot (official)', null, 5], [$video->title(), $video->thumbnail(), $video->position()]);
    }
}
