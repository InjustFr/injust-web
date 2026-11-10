<?php

declare(strict_types=1);

namespace App\Application\Lessons;

use App\Domain\Lessons\LessonOfferRepository;
use App\Domain\Lessons\LessonsPageRepository;
use Symfony\Component\Uid\Ulid;

final readonly class StoredLessonsQueries implements LessonsQueries
{
    public function __construct(
        private LessonsPageRepository $pages,
        private LessonOfferRepository $offers,
    ) {
    }

    public function page(): LessonsPageView
    {
        $text = $this->text();

        return new LessonsPageView($text->intro, $text->faq, $this->offers());
    }

    public function text(): LessonsTextView
    {
        return LessonsTextView::of($this->pages->current());
    }

    public function offers(): array
    {
        return array_map(LessonOfferView::of(...), $this->offers->all());
    }

    public function offer(string $id): ?LessonOfferView
    {
        $offer = Ulid::isValid($id) ? $this->offers->find(Ulid::fromString($id)) : null;

        return null === $offer ? null : LessonOfferView::of($offer);
    }
}
