<?php

declare(strict_types=1);

namespace App\Presentation\Api\Contact;

use App\Domain\Contact\ContactMessage;
use App\Domain\Contact\ContactTopic;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ContactPayload
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: ContactMessage::MAX_NAME, normalizer: 'trim')]
        #[Assert\Regex('/^[^\x00-\x1F\x7F]*$/u')]
        public string $name = '',
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT, normalizer: 'trim')]
        #[Assert\Length(max: ContactMessage::MAX_EMAIL, normalizer: 'trim')]
        public string $email = '',
        public ContactTopic $topic = ContactTopic::General,
        #[Assert\Length(max: ContactMessage::MAX_OFFER_REF)]
        public ?string $offerRef = null,
        #[Assert\Length(min: ContactMessage::MIN_BODY, max: ContactMessage::MAX_BODY, normalizer: 'trim')]
        #[Assert\Regex('/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*$/u')]
        public string $body = '',
        public string $website = '',
    ) {
    }
}
