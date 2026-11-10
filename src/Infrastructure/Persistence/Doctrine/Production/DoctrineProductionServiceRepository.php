<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Production;

use App\Domain\Production\ProductionService;
use App\Domain\Production\ProductionServiceRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineProductionServiceRepository implements ProductionServiceRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(ProductionService $service): void
    {
        $this->entityManager->persist($service);
    }

    public function get(Ulid $id): ProductionService
    {
        return $this->find($id) ?? throw new NotFound('service', $id->toBase32());
    }

    public function find(Ulid $id): ?ProductionService
    {
        return $this->entityManager->find(ProductionService::class, $id);
    }

    public function remove(ProductionService $service): void
    {
        $this->entityManager->remove($service);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(ProductionService::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
