<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Sales;

use App\Domain\Sales\CreditNote;
use App\Domain\Sales\CreditNoteRepository;
use App\Domain\Sales\FiscalYear;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineCreditNoteRepository implements CreditNoteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(CreditNote $creditNote): void
    {
        $this->entityManager->persist($creditNote);
    }

    public function find(Ulid $id): ?CreditNote
    {
        return $this->entityManager->find(CreditNote::class, $id);
    }

    public function byRefund(string $stripeRefundId): ?CreditNote
    {
        return $this->notes()->findOneBy(['stripeRefundId' => $stripeRefundId]);
    }

    public function forOrder(Ulid $orderId): array
    {
        /** @var list<CreditNote> $notes */
        $notes = $this->notes()->findBy(['orderId' => $orderId], ['number' => 'ASC']);

        return $notes;
    }

    public function issuedIn(int $year): array
    {
        [$from, $until] = FiscalYear::bounds($year);
        /** @var list<CreditNote> $notes */
        $notes = $this->notes()->createQueryBuilder('c')
            ->where('c.issuedAt >= :from AND c.issuedAt < :until')
            ->setParameter('from', $from, Types::DATETIMETZ_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIMETZ_IMMUTABLE)
            ->orderBy('c.issuedAt')
            ->addOrderBy('c.number')
            ->getQuery()
            ->getResult();

        return $notes;
    }

    /** @return EntityRepository<CreditNote> */
    private function notes(): EntityRepository
    {
        return $this->entityManager->getRepository(CreditNote::class);
    }
}
