<?php

declare(strict_types=1);

namespace App\Application\Contact\SendContactMessage;

use App\Domain\Contact\ContactTopic;

final readonly class SendContactMessage
{
    public function __construct(
        public string $name,
        public string $email,
        public ContactTopic $topic,
        public string $body,
        public string $locale,
        public ?string $offerRef = null,
    ) {
    }
}
