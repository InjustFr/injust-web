<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Lessons;

use App\Domain\Lessons\LessonsPage;
use App\Domain\Lessons\LessonsPageRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLessonsPageRepository implements LessonsPageRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): LessonsPage
    {
        return $this->entityManager->find(LessonsPage::class, LessonsPage::ID) ?? LessonsPage::initial();
    }

    public function save(LessonsPage $page): void
    {
        $this->entityManager->persist($page);
    }
}
