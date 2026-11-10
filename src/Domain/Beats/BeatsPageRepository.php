<?php

declare(strict_types=1);

namespace App\Domain\Beats;

interface BeatsPageRepository
{
    public function current(): BeatsPage;

    public function save(BeatsPage $page): void;
}
