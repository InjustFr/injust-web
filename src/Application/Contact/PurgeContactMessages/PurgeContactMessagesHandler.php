<?php

declare(strict_types=1);

namespace App\Application\Contact\PurgeContactMessages;

use App\Application\Transaction;
use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactMessageRepository;
use Psr\Clock\ClockInterface;

final readonly class PurgeContactMessagesHandler
{
    public function __construct(
        private ContactMessageRepository $messages,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(PurgeContactMessages $command): void
    {
        $this->messages->removeReceivedBefore($this->clock->now()->modify(ContactMessage::RETENTION));
        $this->transaction->commit();
    }
}
