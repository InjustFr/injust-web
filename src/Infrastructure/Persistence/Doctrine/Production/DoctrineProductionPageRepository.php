<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Production;

use App\Domain\Production\ProductionPage;
use App\Domain\Production\ProductionPageRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineProductionPageRepository implements ProductionPageRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): ProductionPage
    {
        return $this->entityManager->find(ProductionPage::class, ProductionPage::ID) ?? ProductionPage::initial();
    }

    public function save(ProductionPage $page): void
    {
        $this->entityManager->persist($page);
    }
}
