<?php

declare(strict_types=1);

namespace App\Presentation\Web\Site;

use App\Application\Shared\ImageStorage;
use App\Application\Shared\ResizedImage;
use Twig\Attribute\AsTwigFunction;

final readonly class ImageSize
{
    public function __construct(private ImageStorage $storage)
    {
    }

    /** @return array{width: int, height: int}|null */
    #[AsTwigFunction('image_size')]
    public function of(string $id): ?array
    {
        $path = $this->storage->path($id.'.'.ResizedImage::EXTENSION);
        $size = null === $path ? false : getimagesize($path);

        return false === $size ? null : ['width' => $size[0], 'height' => $size[1]];
    }
}
