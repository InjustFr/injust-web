<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Production;

use App\Domain\Production\PortfolioItem;
use App\Domain\Production\PortfolioItemRepository;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final readonly class DoctrinePortfolioItemRepository implements PortfolioItemRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(PortfolioItem $item): void
    {
        $this->entityManager->persist($item);
    }

    public function get(Ulid $id): PortfolioItem
    {
        return $this->entityManager->find(PortfolioItem::class, $id) ?? throw new NotFound('portfolio_item', $id->toBase32());
    }

    public function remove(PortfolioItem $item): void
    {
        $this->entityManager->remove($item);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(PortfolioItem::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
    }
}
