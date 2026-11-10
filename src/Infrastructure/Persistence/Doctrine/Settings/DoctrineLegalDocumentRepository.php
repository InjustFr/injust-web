<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Settings;

use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalDocumentRepository;
use App\Domain\Settings\LegalKind;
use App\Domain\Shared\Exception\NotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineLegalDocumentRepository implements LegalDocumentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(LegalDocument $document): void
    {
        $this->entityManager->persist($document);
    }

    public function find(LegalKind $kind): ?LegalDocument
    {
        return $this->entityManager->find(LegalDocument::class, $kind);
    }

    public function get(LegalKind $kind): LegalDocument
    {
        return $this->find($kind) ?? throw new NotFound('legal_document', $kind->value);
    }

    public function all(): array
    {
        return array_values(array_filter(array_map($this->find(...), LegalKind::cases())));
    }
}
