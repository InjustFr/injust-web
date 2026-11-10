<?php

declare(strict_types=1);

namespace App\Domain\Contact;

use Symfony\Component\Uid\Ulid;

interface ContactMessageRepository
{
    public function add(ContactMessage $message): void;

    public function get(Ulid $id): ContactMessage;

    public function remove(ContactMessage $message): void;

    public function removeReceivedBefore(\DateTimeImmutable $before): void;

    /** @return list<ContactMessage> */
    public function newestFirst(bool $withHandled): array;
}
