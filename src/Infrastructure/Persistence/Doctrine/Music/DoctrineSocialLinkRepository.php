<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Music;

use App\Domain\Music\SocialLink;
use App\Domain\Music\SocialLinkRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineSocialLinkRepository implements SocialLinkRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(SocialLink $item): void
    {
        $this->entityManager->persist($item);
    }

    public function get(Ulid $id): SocialLink
    {
        return $this->entityManager->find(SocialLink::class, $id) ?? throw new NotFound('social_link', $id->toBase32());
    }

    public function remove(SocialLink $item): void
    {
        $this->entityManager->remove($item);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(SocialLink::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
