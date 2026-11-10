<?php

declare(strict_types=1);

namespace App\Domain\Lessons;

interface LessonsPageRepository
{
    public function current(): LessonsPage;

    public function save(LessonsPage $page): void;
}
