<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Beats;

use App\Domain\Beats\BeatsPage;
use App\Domain\Beats\BeatsPageRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineBeatsPageRepository implements BeatsPageRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): BeatsPage
    {
        return $this->entityManager->find(BeatsPage::class, BeatsPage::ID) ?? BeatsPage::initial();
    }

    public function save(BeatsPage $page): void
    {
        $this->entityManager->persist($page);
    }
}
