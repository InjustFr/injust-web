<?php

declare(strict_types=1);

namespace App\Domain\Sales;

interface InvoiceSequenceRepository
{
    public function lock(int $year): InvoiceSequence;
}
