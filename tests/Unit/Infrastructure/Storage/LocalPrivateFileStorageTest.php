<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Domain\Shared\Exception\NotFound;
use App\Infrastructure\Storage\LocalPrivateFileStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final class LocalPrivateFileStorageTest extends TestCase
{
    private const string WAV = "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00";

    private string $directory;

    private string $upload;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/private-'.bin2hex(random_bytes(4));
        $this->upload = sys_get_temp_dir().'/upload-'.bin2hex(random_bytes(4));
        new Filesystem()->dumpFile($this->upload, self::WAV);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->directory, $this->upload]);
    }

    public function testAnUploadIsMovedInAndDescribed(): void
    {
        $storage = new LocalPrivateFileStorage($this->directory);

        $stored = $storage->store($this->upload, 'Riot (final).wav');

        self::assertTrue(Ulid::isValid($stored->id));
        self::assertSame(['Riot (final).wav', \strlen(self::WAV), 'audio/x-wav'], [$stored->originalName, $stored->size, $stored->mimeType]);
        self::assertFileDoesNotExist($this->upload);
        self::assertSame($this->directory.'/'.$stored->id, $storage->path($stored->id));
        self::assertSame(self::WAV, file_get_contents($storage->path($stored->id)));
    }

    public function testTheTypeComesFromTheContentNotTheName(): void
    {
        new Filesystem()->dumpFile($this->upload, 'hello');

        self::assertSame('text/plain', new LocalPrivateFileStorage($this->directory)->store($this->upload, 'a.wav')->mimeType);
    }

    public function testADeletedFileIsGone(): void
    {
        $storage = new LocalPrivateFileStorage($this->directory);
        $stored = $storage->store($this->upload, 'a.wav');

        $storage->delete($stored->id);
        $storage->delete($stored->id);

        self::assertFileDoesNotExist($this->directory.'/'.$stored->id);
        $this->expectException(NotFound::class);
        $storage->path($stored->id);
    }

    public function testOnlyStoredUlidsResolve(): void
    {
        new Filesystem()->dumpFile($this->directory.'/secret', 'x');
        new Filesystem()->dumpFile(\dirname($this->directory).'/'.basename($this->directory).'-outside', 'x');
        $storage = new LocalPrivateFileStorage($this->directory);

        foreach (['secret', '../'.basename($this->directory).'-outside', new Ulid()->toBase32(), strtolower(new Ulid()->toBase32()), ''] as $id) {
            try {
                $storage->path($id);
                self::fail($id.' resolved');
            } catch (NotFound) {
            }
            $storage->delete($id);
        }

        self::assertFileExists($this->directory.'/secret');
        new Filesystem()->remove(\dirname($this->directory).'/'.basename($this->directory).'-outside');
    }
}
