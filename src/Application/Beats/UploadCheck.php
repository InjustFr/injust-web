<?php

declare(strict_types=1);

namespace App\Application\Beats;

use App\Domain\Beats\Deliverable;
use App\Domain\Beats\Exception\BeatFileTooLarge;
use App\Domain\Beats\Exception\UnsupportedBeatFile;
use App\Domain\Beats\FileFormat;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class UploadCheck
{
    public const string PREVIEW = 'preview';

    private const int MEGABYTE = 1048576;

    /** @param array<string, int> $limits */
    public function __construct(
        #[Autowire('%beats.upload_limits%')]
        private array $limits,
    ) {
    }

    public function preview(string $path): int
    {
        return $this->check($path, FileFormat::Mp3, $this->limits[self::PREVIEW]);
    }

    public function deliverable(string $path, Deliverable $deliverable): int
    {
        return $this->check($path, $deliverable->format(), $this->limits[$deliverable->value]);
    }

    private function check(string $path, FileFormat $format, int $limit): int
    {
        $head = is_file($path) ? (string) file_get_contents($path, length: FileFormat::HEAD) : '';
        if (!$format->matches($head)) {
            throw new UnsupportedBeatFile($format->value);
        }
        $size = (int) filesize($path);
        if ($size > $limit) {
            throw new BeatFileTooLarge((int) ceil($limit / self::MEGABYTE));
        }

        return $size;
    }
}
