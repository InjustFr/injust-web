<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Music;

use App\Domain\Music\Platform;
use App\Domain\Music\Release;
use App\Domain\Music\ReleaseRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
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
        return $this->taken(['slug' => $slug], $except);
    }

    public function upcTaken(string $upc, ?Ulid $except): bool
    {
        return $this->taken(['upc' => $upc], $except);
    }

    public function bySlug(string $slug): Release
    {
        return $this->entityManager->getRepository(Release::class)->findOneBy(['slug' => $slug]) ?? throw new NotFound('release', $slug);
    }

    public function countClick(Ulid $id, Platform $platform): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO release_link_click (release_id, platform, clicks) VALUES (:id, :platform, 1) ON CONFLICT (release_id, platform) DO UPDATE SET clicks = release_link_click.clicks + 1',
            ['id' => $id, 'platform' => $platform->value],
            ['id' => UlidType::NAME],
        );
    }

    /** @param array<string, string> $criteria */
    private function taken(array $criteria, ?Ulid $except): bool
    {
        $found = $this->entityManager->getRepository(Release::class)->findOneBy($criteria);

        return null !== $found && (null === $except || !$found->id()->equals($except));
    }
}
