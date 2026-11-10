<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

use App\Domain\Sales\InvoiceSequence;
use App\Domain\Sales\InvoiceSequenceRepository;

final class InMemoryInvoiceSequences implements InvoiceSequenceRepository
{
    /** @var array<int, InvoiceSequence> */
    public array $sequences = [];

    public function __construct(private readonly Journal $journal = new Journal())
    {
    }

    public function lock(int $year): InvoiceSequence
    {
        $this->journal->write('lock sequence '.$year);

        return $this->sequences[$year] ??= InvoiceSequence::start($year);
    }
}
