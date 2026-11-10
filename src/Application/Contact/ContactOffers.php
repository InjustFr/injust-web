<?php

declare(strict_types=1);

namespace App\Application\Contact;

use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Domain\Contact\ContactTopic;

final readonly class ContactOffers
{
    public function __construct(
        private LessonsQueries $lessons,
        private ProductionQueries $production,
    ) {
    }

    public function find(ContactTopic $topic, string $ref, string $locale): ?ContactOffer
    {
        $offer = match ($topic) {
            ContactTopic::Lessons => $this->lessons->offer($ref),
            ContactTopic::Production => $this->production->service($ref),
            default => null,
        };

        return null === $offer ? null : new ContactOffer($offer->id, $offer->title->in($locale));
    }
}
