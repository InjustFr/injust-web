<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Application\Shared\ResizedImage;
use App\Infrastructure\Storage\LocalImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class LocalImageStorageTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/images-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testAStoredImageCanBeFoundListedAndDeleted(): void
    {
        $storage = new LocalImageStorage($this->directory);

        $file = $storage->store(new Ulid(), new ResizedImage('webp', 1, 1));

        self::assertSame($this->directory.'/'.$file, $storage->path($file));
        self::assertSame([$file], $storage->files());
        $storage->delete($file);
        self::assertNull($storage->path($file));
    }

    public function testANameOutsideThePatternIsNeverResolved(): void
    {
        new Filesystem()->dumpFile($this->directory.'/secret.webp', 'x');

        self::assertNull(new LocalImageStorage($this->directory)->path('secret.webp'));
        self::assertSame([], new LocalImageStorage($this->directory)->files());
    }
}
