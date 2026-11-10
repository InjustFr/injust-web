<?php

declare(strict_types=1);

namespace App\Application\Contact;

final readonly class ContactOffer
{
    public function __construct(
        public string $id,
        public string $label,
    ) {
    }
}
