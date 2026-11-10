<?php

declare(strict_types=1);

namespace App\Tests\Support\Sales;

final class Journal
{
    /** @var list<string> */
    public array $entries = [];

    public function write(string $entry): void
    {
        $this->entries[] = $entry;
    }
}
