<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Music;

use App\Domain\Music\MusicText;
use App\Domain\Music\MusicTextRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMusicTextRepository implements MusicTextRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): MusicText
    {
        return $this->entityManager->find(MusicText::class, MusicText::ID) ?? MusicText::initial();
    }

    public function save(MusicText $text): void
    {
        $this->entityManager->persist($text);
    }
}
