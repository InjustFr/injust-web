<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Beats;

use App\Application\Transaction;
use App\Tests\Support\Sales\Journal;

final readonly class FailingTransaction implements Transaction
{
    public function __construct(private Journal $journal)
    {
    }

    public function commit(): void
    {
        $this->journal->write('commit failed');

        throw new \RuntimeException('database down');
    }
}
