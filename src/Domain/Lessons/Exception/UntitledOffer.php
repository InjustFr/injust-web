<?php

declare(strict_types=1);

namespace App\Domain\Lessons\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UntitledOffer extends DomainException
{
    public function __construct()
    {
        parent::__construct('lessons.untitled_offer');
    }
}
