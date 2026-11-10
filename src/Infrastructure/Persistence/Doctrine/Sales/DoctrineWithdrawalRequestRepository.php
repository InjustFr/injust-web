<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Sales;

use App\Domain\Sales\WithdrawalRequest;
use App\Domain\Sales\WithdrawalRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineWithdrawalRequestRepository implements WithdrawalRequestRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(WithdrawalRequest $request): void
    {
        $this->entityManager->persist($request);
    }

    public function forOrder(Ulid $orderId): ?WithdrawalRequest
    {
        return $this->entityManager->getRepository(WithdrawalRequest::class)->findOneBy(['orderId' => $orderId]);
    }
}
