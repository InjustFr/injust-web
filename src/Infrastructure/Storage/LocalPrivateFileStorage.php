<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Shared\PrivateFileStorage;
use App\Application\Shared\StoredFile;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Uid\Ulid;

final readonly class LocalPrivateFileStorage implements PrivateFileStorage
{
    private const string ID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function __construct(
        #[Autowire('%kernel.share_dir%/private')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(string $tmpPath, string $originalName): StoredFile
    {
        $id = new Ulid()->toBase32();
        $mimeType = new MimeTypes()->guessMimeType($tmpPath) ?? 'application/octet-stream';
        $size = new \SplFileInfo($tmpPath)->getSize();
        $this->filesystem->mkdir($this->directory);
        $this->filesystem->rename($tmpPath, $this->directory.'/'.$id);

        return new StoredFile($id, $originalName, $size, $mimeType);
    }

    public function path(string $id): string
    {
        $path = $this->directory.'/'.$id;

        return 1 === preg_match(self::ID_PATTERN, $id) && is_file($path) ? $path : throw new NotFound('file', $id);
    }

    public function delete(string $id): void
    {
        if (1 === preg_match(self::ID_PATTERN, $id)) {
            $this->filesystem->remove($this->directory.'/'.$id);
        }
    }
}
