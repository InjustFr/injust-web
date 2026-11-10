<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface LegalDocumentRepository
{
    public function add(LegalDocument $document): void;

    public function find(LegalKind $kind): ?LegalDocument;

    public function get(LegalKind $kind): LegalDocument;

    /** @return list<LegalDocument> */
    public function all(): array;
}
