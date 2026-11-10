<?php

declare(strict_types=1);

namespace App\Application\Shared;

use Symfony\Component\Uid\Ulid;

final readonly class Images
{
    public function __construct(private ImageStorage $storage)
    {
    }

    public function store(ResizedImage $image): string
    {
        $id = new Ulid();
        $this->storage->store($id, $image);

        return $id->toBase32();
    }

    public function discard(?string $image): void
    {
        if (null !== $image) {
            $this->storage->delete($image.'.'.ResizedImage::EXTENSION);
        }
    }
}
