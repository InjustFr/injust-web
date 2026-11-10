<?php

declare(strict_types=1);

namespace App\Application\Settings\EditLegalDocument;

use App\Application\Settings\LegalDocumentView;
use App\Application\Transaction;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalDocumentRepository;
use Psr\Clock\ClockInterface;

final readonly class EditLegalDocumentHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(EditLegalDocument $command): LegalDocumentView
    {
        $now = $this->clock->now();
        $document = $this->documents->find($command->kind);
        if (null === $document) {
            $document = LegalDocument::draft($command->kind, $command->body, $now);
            $this->documents->add($document);
        } else {
            $document->edit($command->body, $now);
        }
        $this->transaction->commit();

        return LegalDocumentView::of($document);
    }
}
