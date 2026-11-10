<?php

declare(strict_types=1);

namespace App\Application\Contact;

interface ContactQueries
{
    /** @return list<ContactMessageView> */
    public function messages(bool $withHandled): array;
}
