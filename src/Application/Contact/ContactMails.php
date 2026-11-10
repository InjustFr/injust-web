<?php

declare(strict_types=1);

namespace App\Application\Contact;

interface ContactMails
{
    public function notify(string $recipient, ContactMessageView $message): void;

    public function acknowledge(ContactMessageView $message): void;
}
