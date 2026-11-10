<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Shared;

use App\Domain\Shared\ImageFocus;
use App\Domain\Shared\ImageFocusRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineImageFocusRepository implements ImageFocusRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $image): ImageFocus
    {
        return $this->entityManager->find(ImageFocus::class, $image) ?? ImageFocus::centered($image);
    }

    public function all(): array
    {
        return $this->entityManager->getRepository(ImageFocus::class)->findAll();
    }

    public function save(ImageFocus $focus): void
    {
        $this->entityManager->persist($focus);
    }

    public function remove(ImageFocus $focus): void
    {
        $this->entityManager->remove($focus);
    }

    public function forget(string $image): void
    {
        $this->entityManager->getConnection()->delete('image_focus', ['image' => $image]);
    }
}
