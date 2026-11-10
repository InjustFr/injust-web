<?php

declare(strict_types=1);

namespace App\Application\Contact\SendContactMessage;

use App\Application\Contact\ContactMails;
use App\Application\Contact\ContactMessageView;
use App\Application\Contact\ContactOffers;
use App\Application\Settings\SettingsQueries;
use App\Application\Transaction;
use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactMessageRepository;
use Psr\Clock\ClockInterface;

final readonly class SendContactMessageHandler
{
    public function __construct(
        private ContactMessageRepository $messages,
        private ContactOffers $offers,
        private SettingsQueries $settings,
        private ContactMails $mails,
        private ClockInterface $clock,
        private Transaction $transaction,
    ) {
    }

    public function __invoke(SendContactMessage $command): ContactMessageView
    {
        $offer = $this->offers->find($command->topic, $command->offerRef ?? '', $command->locale);
        $message = ContactMessage::receive($command->name, $command->email, $command->topic, $command->body, $command->locale, $this->clock->now(), $offer?->id, $offer?->label);
        $this->messages->add($message);
        $this->transaction->commit();

        $view = ContactMessageView::of($message);
        $recipient = $this->settings->current()->contactEmail;
        if ('' !== $recipient) {
            $this->mails->notify($recipient, $view);
        }
        $this->mails->acknowledge($view);

        return $view;
    }
}
