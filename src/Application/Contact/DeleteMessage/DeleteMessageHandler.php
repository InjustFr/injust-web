<?php

declare(strict_types=1);

namespace App\Application\Contact\DeleteMessage;

use App\Application\Transaction;
use App\Domain\Contact\ContactMessageRepository;

final readonly class DeleteMessageHandler
{
    public function __construct(
        private ContactMessageRepository $messages,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(DeleteMessage $command): void
    {
        $this->messages->remove($this->messages->get($command->id));
        $this->transaction->commit();
    }
}
