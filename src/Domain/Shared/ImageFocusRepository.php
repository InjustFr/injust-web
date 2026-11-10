<?php

declare(strict_types=1);

namespace App\Domain\Shared;

interface ImageFocusRepository
{
    public function get(string $image): ImageFocus;

    /** @return list<ImageFocus> */
    public function all(): array;

    public function save(ImageFocus $focus): void;

    public function remove(ImageFocus $focus): void;

    public function forget(string $image): void;
}
