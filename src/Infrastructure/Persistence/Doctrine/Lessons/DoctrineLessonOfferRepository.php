<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Lessons;

use App\Domain\Lessons\LessonOffer;
use App\Domain\Lessons\LessonOfferRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineLessonOfferRepository implements LessonOfferRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(LessonOffer $offer): void
    {
        $this->entityManager->persist($offer);
    }

    public function get(Ulid $id): LessonOffer
    {
        return $this->find($id) ?? throw new NotFound('offer', $id->toBase32());
    }

    public function find(Ulid $id): ?LessonOffer
    {
        return $this->entityManager->find(LessonOffer::class, $id);
    }

    public function remove(LessonOffer $offer): void
    {
        $this->entityManager->remove($offer);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(LessonOffer::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
