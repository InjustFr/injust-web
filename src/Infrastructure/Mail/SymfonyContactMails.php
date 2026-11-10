<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Contact\ContactMails;
use App\Application\Contact\ContactMessageView;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyContactMails implements ContactMails
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private RateLimiterFactoryInterface $contactRecipientLimiter,
        private RateLimiterFactoryInterface $contactAcknowledgementLimiter,
        #[Autowire(env: 'MAILER_SENDER')]
        private string $sender,
        #[Autowire(param: 'kernel.default_locale')]
        private string $ownerLocale,
    ) {
    }

    public function notify(string $recipient, ContactMessageView $message): void
    {
        $topic = $this->translator->trans('contact.topic.'.$message->topic, [], 'emails', $this->ownerLocale);
        $this->send(new TemplatedEmail()
            ->to($recipient)
            ->replyTo(new Address($message->email, $message->name))
            ->subject($this->translator->trans('contact.notification.subject', ['name' => $message->name, 'topic' => $topic], 'emails', $this->ownerLocale))
            ->textTemplate('email/contact_notification.txt.twig')
            ->context(['contact' => $message, 'topic' => $topic])
            ->locale($this->ownerLocale), $message);
    }

    public function acknowledge(ContactMessageView $message): void
    {
        if (!$this->contactRecipientLimiter->create(mb_strtolower($message->email))->consume()->isAccepted()
            || !$this->contactAcknowledgementLimiter->create('all')->consume()->isAccepted()) {
            return;
        }
        $this->send(new TemplatedEmail()
            ->to($message->email)
            ->subject($this->translator->trans('contact.acknowledgement.subject', [], 'emails', $message->locale))
            ->textTemplate('email/contact_acknowledgement.txt.twig')
            ->context(['offer' => $message->offerLabel])
            ->locale($message->locale), $message);
    }

    private function send(TemplatedEmail $email, ContactMessageView $message): void
    {
        try {
            $this->mailer->send($email->from(Address::create($this->sender)));
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('A contact mail could not be sent.', ['message' => $message->id, 'error' => $exception::class]);
        }
    }
}
