<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class NegativePrice extends DomainException
{
    public function __construct(int $cents)
    {
        parent::__construct('shared.negative_price', ['cents' => $cents]);
    }
}
