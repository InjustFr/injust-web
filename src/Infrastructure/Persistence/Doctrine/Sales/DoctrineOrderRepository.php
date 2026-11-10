<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Sales;

use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    public function get(Ulid $id): Order
    {
        return $this->find($id) ?? throw new NotFound('order', $id->toBase32());
    }

    public function find(Ulid $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id);
    }

    public function lockBySession(string $sessionId): ?Order
    {
        return $this->lock('checkoutSessionId', $sessionId);
    }

    public function lockByPaymentIntent(string $paymentIntentId): ?Order
    {
        return $this->lock('paymentIntentId', $paymentIntentId);
    }

    public function byReference(string $reference): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['reference' => $reference]);
    }

    public function lockByReference(string $reference): ?Order
    {
        return $this->lock('reference', $reference);
    }

    public function bySession(string $sessionId): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['checkoutSessionId' => $sessionId]);
    }

    public function byPaymentIntent(string $paymentIntentId): ?Order
    {
        return $this->entityManager->getRepository(Order::class)->findOneBy(['paymentIntentId' => $paymentIntentId]);
    }

    public function newestFirst(?OrderStatus $status, ?int $year): array
    {
        $query = $this->orders()->orderBy('o.createdAt', 'DESC')->addOrderBy('o.id', 'DESC');
        if (null !== $status) {
            $query->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if (null !== $year) {
            self::within($query, 'o.createdAt', $year);
        }

        return self::list($query);
    }

    public function paidIn(int $year): array
    {
        $query = $this->orders()
            ->where('o.status IN (:statuses)')
            ->setParameter('statuses', [OrderStatus::Paid, OrderStatus::Withdrawn, OrderStatus::Refunded])
            ->orderBy('o.paidAt')
            ->addOrderBy('o.id');

        return self::list(self::within($query, 'o.paidAt', $year));
    }

    public function removeExpiredBefore(\DateTimeImmutable $before): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(Order::class, 'o')
            ->where('o.status = :expired AND o.createdAt < :before')
            ->setParameter('expired', OrderStatus::Expired)
            ->setParameter('before', $before, Types::DATETIMETZ_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    private function lock(string $field, string $value): ?Order
    {
        $order = $this->orders()
            ->where('o.'.$field.' = :value')
            ->setParameter('value', $value)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $order instanceof Order ? $order : null;
    }

    private function orders(): QueryBuilder
    {
        return $this->entityManager->getRepository(Order::class)->createQueryBuilder('o');
    }

    private static function within(QueryBuilder $query, string $field, int $year): QueryBuilder
    {
        [$from, $until] = FiscalYear::bounds($year);

        return $query
            ->andWhere($field.' >= :from AND '.$field.' < :until')
            ->setParameter('from', $from, Types::DATETIMETZ_IMMUTABLE)
            ->setParameter('until', $until, Types::DATETIMETZ_IMMUTABLE);
    }

    /** @return list<Order> */
    private static function list(QueryBuilder $query): array
    {
        /** @var list<Order> $orders */
        $orders = $query->getQuery()->getResult();

        return $orders;
    }
}
