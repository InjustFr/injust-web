<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\LegalDocumentRepository;
use App\Domain\Settings\LegalKind;
use App\Domain\Settings\SettingsRepository;

final readonly class StoredSettingsQueries implements SettingsQueries
{
    public function __construct(
        private SettingsRepository $settings,
        private LegalDocumentRepository $documents,
    ) {
    }

    public function current(): SettingsView
    {
        return SettingsView::of($this->settings->current());
    }

    public function legalDocument(LegalKind $kind): LegalDocumentView
    {
        return LegalDocumentView::of($this->documents->get($kind));
    }

    public function legalDocuments(): array
    {
        return array_map(LegalDocumentView::of(...), $this->documents->all());
    }
}
