<?php

declare(strict_types=1);

namespace App\Application\Contact;

use App\Domain\Contact\ContactMessageRepository;

final readonly class StoredContactQueries implements ContactQueries
{
    public function __construct(private ContactMessageRepository $messages)
    {
    }

    public function messages(bool $withHandled): array
    {
        return array_map(ContactMessageView::of(...), $this->messages->newestFirst($withHandled));
    }
}
