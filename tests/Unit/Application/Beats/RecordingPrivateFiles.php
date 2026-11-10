<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Shared\PrivateFileStorage;
use App\Application\Shared\StoredFile;
use App\Tests\Support\Sales\Journal;

final class RecordingPrivateFiles implements PrivateFileStorage
{
    private int $stored = 0;

    public function __construct(private readonly Journal $journal)
    {
    }

    public function store(string $tmpPath, string $originalName): StoredFile
    {
        $id = 'private'.++$this->stored;
        $this->journal->write('store '.$id.' '.basename($tmpPath).' as '.$originalName);

        return new StoredFile($id, $originalName, (int) filesize($tmpPath), 'audio/x-wav');
    }

    public function path(string $id): string
    {
        return '/private/'.$id;
    }

    public function delete(string $id): void
    {
        $this->journal->write('delete '.$id);
    }
}
