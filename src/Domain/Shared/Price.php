<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Shared\Exception\NegativePrice;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class Price
{
    public const string CURRENCY = 'EUR';

    #[ORM\Column]
    public int $cents;

    public function __construct(int $cents)
    {
        if ($cents < 0) {
            throw new NegativePrice($cents);
        }
        $this->cents = $cents;
    }
}
