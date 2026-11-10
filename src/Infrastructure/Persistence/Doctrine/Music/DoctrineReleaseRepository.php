<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Music;

use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineReleaseRepository implements ReleaseRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Release $item): void
    {
        $this->entityManager->persist($item);
    }

    public function get(Ulid $id): Release
    {
        return $this->entityManager->find(Release::class, $id) ?? throw new NotFound('release', $id->toBase32());
    }

    public function remove(Release $item): void
    {
        $this->entityManager->remove($item);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Release::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function slugTaken(string $slug, ?Ulid $except): bool
    {
        $found = $this->entityManager->getRepository(Release::class)->findOneBy(['slug' => $slug]);

        return null !== $found && (null === $except || !$found->id()->equals($except));
    }
}
