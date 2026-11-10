<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Music;

use App\Domain\Music\Video;
use App\Domain\Music\VideoRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineVideoRepository implements VideoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Video $item): void
    {
        $this->entityManager->persist($item);
    }

    public function get(Ulid $id): Video
    {
        return $this->entityManager->find(Video::class, $id) ?? throw new NotFound('video', $id->toBase32());
    }

    public function remove(Video $item): void
    {
        $this->entityManager->remove($item);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(Video::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
