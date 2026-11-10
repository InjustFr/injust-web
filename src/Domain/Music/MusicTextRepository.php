<?php

declare(strict_types=1);

namespace App\Domain\Music;

interface MusicTextRepository
{
    public function current(): MusicText;

    public function save(MusicText $text): void;
}
