<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\LegalKind;

interface SettingsQueries
{
    public function current(): SettingsView;

    public function legalDocument(LegalKind $kind): LegalDocumentView;

    /** @return list<LegalDocumentView> */
    public function legalDocuments(): array;
}
