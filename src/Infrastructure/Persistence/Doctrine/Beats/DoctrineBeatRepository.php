<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Beats;

use App\Domain\Beats\Beat;
use App\Domain\Beats\BeatRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineBeatRepository implements BeatRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Beat $beat): void
    {
        $this->entityManager->persist($beat);
    }

    public function get(Ulid $id): Beat
    {
        return $this->find($id) ?? throw new NotFound('beat', $id->toBase32());
    }

    public function find(Ulid $id): ?Beat
    {
        return $this->entityManager->find(Beat::class, $id);
    }

    public function lock(Ulid $id): ?Beat
    {
        return $this->entityManager->find(Beat::class, $id, LockMode::PESSIMISTIC_WRITE);
    }

    public function remove(Beat $beat): void
    {
        $this->entityManager->remove($beat);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Beat::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function slugTaken(string $slug, ?Ulid $except): bool
    {
        $beat = $this->entityManager->getRepository(Beat::class)->findOneBy(['slug' => $slug]);

        return null !== $beat && !$beat->id()->equals($except);
    }
}
