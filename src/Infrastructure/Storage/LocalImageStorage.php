<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Shared\ImageStorage;
use App\Application\Shared\ResizedImage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Ulid;

final readonly class LocalImageStorage implements ImageStorage
{
    private const string FILE_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}(-[1-9][0-9]{0,4})?\.webp$/';

    public function __construct(
        #[Autowire('%kernel.share_dir%/images')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function store(Ulid $id, ResizedImage $image): string
    {
        $file = $id->toBase32().'.'.ResizedImage::EXTENSION;
        $this->filesystem->dumpFile($this->directory.'/'.$file, $image->bytes);

        return $file;
    }

    public function storeVariant(Ulid $id, ResizedImage $image): string
    {
        $file = $id->toBase32().'-'.$image->width.'.'.ResizedImage::EXTENSION;
        $this->filesystem->dumpFile($this->directory.'/'.$file, $image->bytes);

        return $file;
    }

    public function widths(string $id): array
    {
        $widths = [];
        foreach ($this->variants($id) as $file) {
            $widths[] = (int) substr(basename($file, '.'.ResizedImage::EXTENSION), 27);
        }
        sort($widths);

        return $widths;
    }

    public function delete(string $file): void
    {
        $path = $this->path($file);
        if (null !== $path) {
            $this->filesystem->remove([$path, ...$this->variants(basename($file, '.'.ResizedImage::EXTENSION))]);
        }
    }

    /** @return list<string> */
    private function variants(string $id): array
    {
        if (1 !== preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id)) {
            return [];
        }

        return array_values(array_filter(glob($this->directory.'/'.$id.'-*.'.ResizedImage::EXTENSION) ?: [], fn (string $path): bool => null !== $this->path(basename($path))));
    }

    public function files(): array
    {
        $files = array_map(basename(...), glob($this->directory.'/*.'.ResizedImage::EXTENSION) ?: []);

        return array_values(array_filter($files, static fn (string $file): bool => 1 === preg_match(self::FILE_PATTERN, $file)));
    }

    public function path(string $file): ?string
    {
        if (1 !== preg_match(self::FILE_PATTERN, $file)) {
            return null;
        }

        $path = $this->directory.'/'.$file;

        return is_file($path) ? $path : null;
    }
}
