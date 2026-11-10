<?php

declare(strict_types=1);

namespace App\Domain\Music;

final readonly class Track
{
    public int $position;
    public string $title;
    public ?int $durationSeconds;

    public function __construct(int $position, string $title, ?int $durationSeconds)
    {
        $this->position = $position;
        $this->title = trim($title);
        $this->durationSeconds = $durationSeconds;
    }
}
