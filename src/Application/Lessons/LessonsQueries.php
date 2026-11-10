<?php

declare(strict_types=1);

namespace App\Application\Lessons;

interface LessonsQueries
{
    public function page(): LessonsPageView;

    public function text(): LessonsTextView;

    /** @return list<LessonOfferView> */
    public function offers(): array;

    public function offer(string $id): ?LessonOfferView;
}
