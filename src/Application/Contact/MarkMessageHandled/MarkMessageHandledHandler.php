<?php

declare(strict_types=1);

namespace App\Application\Contact\MarkMessageHandled;

use App\Application\Contact\ContactMessageView;
use App\Application\Transaction;
use App\Domain\Contact\ContactMessageRepository;
use Psr\Clock\ClockInterface;

final readonly class MarkMessageHandledHandler
{
    public function __construct(
        private ContactMessageRepository $messages,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(MarkMessageHandled $command): ContactMessageView
    {
        $message = $this->messages->get($command->id);
        if ($command->handled) {
            $message->markHandled($this->clock->now());
        } else {
            $message->markUnhandled();
        }
        $this->transaction->commit();

        return ContactMessageView::of($message);
    }
}
