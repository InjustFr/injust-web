<?php

declare(strict_types=1);

namespace App\Domain\Production\Exception;

use App\Domain\Production\PortfolioItemDetails;
use App\Domain\Shared\Exception\DomainException;

final class InvalidYear extends DomainException
{
    public function __construct()
    {
        parent::__construct('production.invalid_year', ['min' => PortfolioItemDetails::MIN_YEAR, 'max' => PortfolioItemDetails::MAX_YEAR]);
    }
}
