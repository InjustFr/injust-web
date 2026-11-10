<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Seo;

use App\Domain\Seo\PageKey;
use App\Domain\Seo\PageSeo;
use App\Domain\Seo\PageSeoRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePageSeoRepository implements PageSeoRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(PageKey $key): PageSeo
    {
        return $this->entityManager->find(PageSeo::class, $key) ?? PageSeo::blank($key);
    }

    public function save(PageSeo $page): void
    {
        $this->entityManager->persist($page);
    }
}
