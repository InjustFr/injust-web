<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Shared\AudioStorage;
use App\Tests\Support\Sales\Journal;

final class RecordingAudio implements AudioStorage
{
    private int $stored = 0;

    public function __construct(private readonly Journal $journal)
    {
    }

    public function store(string $tmpPath): string
    {
        $id = 'audio'.++$this->stored;
        $this->journal->write('store '.$id);

        return $id;
    }

    public function path(string $id): string
    {
        return '/audio/'.$id;
    }

    public function delete(string $id): void
    {
        $this->journal->write('delete '.$id);
    }
}
