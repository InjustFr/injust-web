<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

final class RecordingMailer implements MailerInterface
{
    /** @var list<TemplatedEmail> */
    public array $sent = [];

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        \assert($message instanceof TemplatedEmail);
        $this->sent[] = $message;
    }
}
