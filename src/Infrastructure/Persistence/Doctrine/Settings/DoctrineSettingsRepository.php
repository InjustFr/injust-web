<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Settings;

use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSettingsRepository implements SettingsRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function current(): Settings
    {
        return $this->entityManager->find(Settings::class, Settings::ID) ?? Settings::initial();
    }

    public function save(Settings $settings): void
    {
        $this->entityManager->persist($settings);
    }
}
