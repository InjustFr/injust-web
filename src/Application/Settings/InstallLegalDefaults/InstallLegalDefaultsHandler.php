<?php

declare(strict_types=1);

namespace App\Application\Settings\InstallLegalDefaults;

use App\Application\Settings\LegalDefaults;
use App\Application\Transaction;
use App\Domain\Settings\LegalDocument;
use App\Domain\Settings\LegalDocumentRepository;
use App\Domain\Settings\LegalKind;
use Psr\Clock\ClockInterface;

final readonly class InstallLegalDefaultsHandler
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private LegalDefaults $defaults,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(InstallLegalDefaults $command): void
    {
        foreach (LegalKind::cases() as $kind) {
            $body = $this->defaults->body($kind);
            if ($body->isEmpty() || null !== $this->documents->find($kind)) {
                continue;
            }
            $this->documents->add(LegalDocument::draft($kind, $body, $this->clock->now()));
        }
        $this->transaction->commit();
    }
}
