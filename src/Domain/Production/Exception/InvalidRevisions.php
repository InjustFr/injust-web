<?php

declare(strict_types=1);

namespace App\Domain\Production\Exception;

use App\Domain\Production\ProductionServiceDetails;
use App\Domain\Shared\Exception\DomainException;

final class InvalidRevisions extends DomainException
{
    public function __construct()
    {
        parent::__construct('production.invalid_revisions', ['min' => 0, 'max' => ProductionServiceDetails::MAX_REVISIONS]);
    }
}
