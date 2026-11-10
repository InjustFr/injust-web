<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\Shared\ImageFocusRepository;

final readonly class ImageFocusQueries
{
    public function __construct(private ImageFocusRepository $focuses)
    {
    }

    public function of(string $image): ImageFocusView
    {
        return ImageFocusView::of($this->focuses->get($image));
    }

    /** @return array<string, ImageFocusView> */
    public function all(): array
    {
        $views = [];
        foreach ($this->focuses->all() as $focus) {
            $views[$focus->image()] = ImageFocusView::of($focus);
        }

        return $views;
    }
}
