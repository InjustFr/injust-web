<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Sales;

use App\Domain\Sales\InvoiceSequence;
use App\Domain\Sales\InvoiceSequenceRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

final readonly class DoctrineInvoiceSequenceRepository implements InvoiceSequenceRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function lock(int $year): InvoiceSequence
    {
        $this->entityManager->getConnection()->executeStatement('INSERT INTO invoice_sequence (year, last) VALUES (?, 0) ON CONFLICT (year) DO NOTHING', [$year]);
        $sequence = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(InvoiceSequence::class, 's')
            ->where('s.year = :year')
            ->setParameter('year', $year)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getSingleResult();
        \assert($sequence instanceof InvoiceSequence);

        return $sequence;
    }
}
