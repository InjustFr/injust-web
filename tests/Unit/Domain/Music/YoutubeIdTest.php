<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Music;

use App\Domain\Music\Exception\InvalidYoutubeUrl;
use App\Domain\Music\YoutubeId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class YoutubeIdTest extends TestCase
{
    /** @return iterable<array{string}> */
    public static function accepted(): iterable
    {
        yield 'bare id' => [' dQw4w9WgXcQ '];
        yield 'short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc'];
        yield 'watch page' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s'];
        yield 'watch page, v not first' => ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ'];
        yield 'mobile watch page' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'music watch page' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'without www, over http' => ['http://youtube.com/watch?v=dQw4w9WgXcQ'];
        yield 'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'];
        yield 'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?start=3'];
        yield 'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ'];
        yield 'privacy embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'];
    }

    #[DataProvider('accepted')]
    public function testTheIdIsReadFromEveryKindOfLink(string $url): void
    {
        self::assertSame('dQw4w9WgXcQ', YoutubeId::parse($url)->value);
    }

    public function testDashesAndUnderscoresBelongToTheId(): void
    {
        self::assertSame('a-b_c-d_e-f', YoutubeId::parse('https://youtu.be/a-b_c-d_e-f')->value);
    }

    public function testTheEmbedAndWatchUrls(): void
    {
        $id = YoutubeId::parse('dQw4w9WgXcQ');

        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $id->embedUrl());
        self::assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $id->watchUrl());
    }

    /** @return iterable<array{string}> */
    public static function refused(): iterable
    {
        yield 'too short' => ['dQw4w9WgXc'];
        yield 'too long' => ['dQw4w9WgXcQQ'];
        yield 'bad character' => ['dQw4w9WgXc!'];
        yield 'another site' => ['https://vimeo.com/watch?v=dQw4w9WgXcQ'];
        yield 'a site ending like youtube' => ['https://notyoutube.com/watch?v=dQw4w9WgXcQ'];
        yield 'watch page without v' => ['https://www.youtube.com/watch?list=PL123'];
        yield 'watch page with an array v' => ['https://www.youtube.com/watch?v[]=dQw4w9WgXcQ'];
        yield 'channel page' => ['https://www.youtube.com/@injust'];
        yield 'short link with a long id' => ['https://youtu.be/dQw4w9WgXcQQ'];
        yield 'shorts with a short id' => ['https://www.youtube.com/shorts/dQw4w9'];
        yield 'nested path' => ['https://www.youtube.com/x/shorts/dQw4w9WgXcQ'];
        yield 'another scheme' => ['ftp://youtu.be/dQw4w9WgXcQ'];
        yield 'empty' => [''];
    }

    #[DataProvider('refused')]
    public function testAnythingElseIsRefused(string $url): void
    {
        try {
            YoutubeId::parse($url);
            self::fail('The link was accepted.');
        } catch (InvalidYoutubeUrl $refused) {
            self::assertSame(['music.invalid_youtube_url', ['url' => trim($url)]], [$refused->getMessage(), $refused->parameters()]);
        }
    }
}
