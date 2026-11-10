<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Shared\AudioStorage;
use App\Domain\Shared\Exception\NotFound;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final readonly class LocalAudioStorage implements AudioStorage
{
    private const string ID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/';

    public function __construct(
        #[Autowire('%kernel.share_dir%/audio')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(string $tmpPath): string
    {
        $id = new Ulid()->toBase32();
        $this->filesystem->mkdir($this->directory);
        $this->filesystem->rename($tmpPath, $this->file($id));

        return $id;
    }

    public function path(string $id): string
    {
        $path = $this->file($id);

        return 1 === preg_match(self::ID_PATTERN, $id) && is_file($path) ? $path : throw new NotFound('file', $id);
    }

    public function delete(string $id): void
    {
        if (1 === preg_match(self::ID_PATTERN, $id)) {
            $this->filesystem->remove($this->file($id));
        }
    }

    private function file(string $id): string
    {
        return $this->directory.'/'.$id.'.mp3';
    }
}
