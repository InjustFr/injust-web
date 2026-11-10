<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Atomic;
use App\Application\Transaction;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineTransaction implements Transaction, Atomic
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function commit(): void
    {
        $this->entityManager->flush();
    }

    public function atomically(callable $work): mixed
    {
        return $this->entityManager->wrapInTransaction(static fn (): mixed => $work());
    }
}
