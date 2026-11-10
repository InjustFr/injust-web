<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Links;

use App\Domain\Links\BioLink;
use App\Domain\Links\BioLinkRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrineBioLinkRepository implements BioLinkRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(BioLink $link): void
    {
        $this->entityManager->persist($link);
    }

    public function get(Ulid $id): BioLink
    {
        return $this->entityManager->find(BioLink::class, $id) ?? throw new NotFound('bio_link', $id->toBase32());
    }

    public function remove(BioLink $link): void
    {
        $this->entityManager->remove($link);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(BioLink::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function countClick(Ulid $id): ?string
    {
        $url = $this->entityManager->getConnection()->fetchOne(
            'UPDATE bio_link SET clicks = clicks + 1 WHERE id = :id AND visible = true RETURNING url',
            ['id' => $id],
            ['id' => UlidType::NAME],
        );

        return \is_string($url) ? $url : null;
    }
}
