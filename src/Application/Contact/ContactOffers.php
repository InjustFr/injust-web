<?php

declare(strict_types=1);

namespace App\Application\Contact;

use App\Application\Beats\BeatsQueries;
use App\Application\Lessons\LessonsQueries;
use App\Application\Production\ProductionQueries;
use App\Domain\Contact\ContactTopic;

final readonly class ContactOffers
{
    public function __construct(
        private LessonsQueries $lessons,
        private ProductionQueries $production,
        private BeatsQueries $beats,
    ) {
    }

    public function find(ContactTopic $topic, string $ref, string $locale): ?ContactOffer
    {
        if (ContactTopic::Beats === $topic) {
            $beat = $this->beats->beat($ref);

            return null === $beat ? null : new ContactOffer($beat->id, $beat->title);
        }
        $offer = match ($topic) {
            ContactTopic::Lessons => $this->lessons->offer($ref),
            ContactTopic::Production => $this->production->service($ref),
            default => null,
        };

        return null === $offer ? null : new ContactOffer($offer->id, $offer->title->in($locale));
    }
}
