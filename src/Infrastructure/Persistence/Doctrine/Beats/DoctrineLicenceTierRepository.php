<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Beats;

use App\Domain\Beats\LicenceTier;
use App\Domain\Beats\LicenceTierRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineLicenceTierRepository implements LicenceTierRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(LicenceTier $tier): void
    {
        $this->entityManager->persist($tier);
    }

    public function get(Ulid $id): LicenceTier
    {
        return $this->entityManager->find(LicenceTier::class, $id) ?? throw new NotFound('licence_tier', $id->toBase32());
    }

    public function remove(LicenceTier $tier): void
    {
        $this->entityManager->remove($tier);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(LicenceTier::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function exclusiveTaken(?Ulid $except): bool
    {
        $exclusive = $this->entityManager->getRepository(LicenceTier::class)->findOneBy(['exclusive' => true]);

        return null !== $exclusive && !$exclusive->id()->equals($except);
    }
}
