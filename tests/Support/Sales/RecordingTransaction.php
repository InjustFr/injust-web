<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Application\Atomic;
use App\Application\Transaction;

final readonly class RecordingTransaction implements Transaction, Atomic
{
    public function __construct(private Journal $journal)
    {
    }

    public function commit(): void
    {
        $this->journal->write('commit');
    }

    public function atomically(callable $work): mixed
    {
        $this->journal->write('begin');
        try {
            $result = $work();
        } catch (\Throwable $failure) {
            $this->journal->write('rollback');
            throw $failure;
        }
        $this->journal->write('end');

        return $result;
    }
}
