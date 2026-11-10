<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Domain\Shared\Exception\UnsupportedImage;
use App\Infrastructure\Storage\GdImageResizer;
use PHPUnit\Framework\TestCase;

final class GdImageResizerTest extends TestCase
{
    public function testALargeImageShrinksToWebp(): void
    {
        $image = new GdImageResizer()->resize(__DIR__.'/../../../Fixtures/large-fern.png', 400);

        self::assertSame(400, max($image->width, $image->height));
        self::assertStringStartsWith('RIFF', $image->bytes);
    }

    public function testAFileThatIsNotAnImageIsRefused(): void
    {
        $this->expectException(UnsupportedImage::class);

        new GdImageResizer()->resize(__DIR__.'/../../../Fixtures/not-an-image.png');
    }
}
