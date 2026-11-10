<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared;

use App\Application\Shared\Images;
use App\Application\Shared\ImageStorage;
use App\Application\Shared\ResizedImage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ImagesTest extends TestCase
{
    public function testAnImageIsStoredUnderANewUlid(): void
    {
        $image = new ResizedImage('bytes', 2, 1);
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects($this->once())->method('store')->with(self::isInstanceOf(Ulid::class), self::identicalTo($image))->willReturn('ignored.webp');

        self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', new Images($storage)->store($image));
    }

    public function testADiscardedImageLosesItsFile(): void
    {
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects($this->once())->method('delete')->with('01JCOVER.webp');

        new Images($storage)->discard('01JCOVER');
    }

    public function testDiscardingNoImageTouchesNothing(): void
    {
        $storage = $this->createMock(ImageStorage::class);
        $storage->expects($this->never())->method('delete');

        new Images($storage)->discard(null);
    }
}
