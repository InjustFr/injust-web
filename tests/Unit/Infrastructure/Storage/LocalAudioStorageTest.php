<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Domain\Shared\Exception\NotFound;
use App\Infrastructure\Storage\LocalAudioStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class LocalAudioStorageTest extends TestCase
{
    private string $directory;

    private string $upload;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/audio-'.bin2hex(random_bytes(4));
        $this->upload = sys_get_temp_dir().'/upload-'.bin2hex(random_bytes(4));
        new Filesystem()->dumpFile($this->upload, 'ID3preview');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->directory, $this->upload]);
    }

    public function testAPreviewIsMovedInFoundAndDeleted(): void
    {
        $storage = new LocalAudioStorage($this->directory);

        $id = $storage->store($this->upload);

        self::assertTrue(Ulid::isValid($id));
        self::assertFileDoesNotExist($this->upload);
        self::assertSame($this->directory.'/'.$id.'.mp3', $storage->path($id));
        self::assertSame('ID3preview', file_get_contents($storage->path($id)));

        $storage->delete($id);
        $storage->delete($id);

        self::assertFileDoesNotExist($this->directory.'/'.$id.'.mp3');
        $this->expectException(NotFound::class);
        $storage->path($id);
    }

    public function testOnlyStoredUlidsResolve(): void
    {
        new Filesystem()->dumpFile($this->directory.'/secret.mp3', 'x');
        $storage = new LocalAudioStorage($this->directory);

        foreach (['secret', '../audio/secret', new Ulid()->toBase32(), ''] as $id) {
            try {
                $storage->path($id);
                self::fail($id.' resolved');
            } catch (NotFound) {
            }
            $storage->delete($id);
        }

        self::assertFileExists($this->directory.'/secret.mp3');
    }
}
