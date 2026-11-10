<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_sequence')]
class InvoiceSequence
{
    #[ORM\Id]
    #[ORM\Column]
    private int $year;

    #[ORM\Column]
    private int $last = 0;

    private function __construct(int $year)
    {
        $this->year = $year;
    }

    public static function start(int $year): self
    {
        return new self($year);
    }

    public function next(): string
    {
        ++$this->last;

        return \sprintf('%d-%04d', $this->year, $this->last);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function last(): int
    {
        return $this->last;
    }
}
