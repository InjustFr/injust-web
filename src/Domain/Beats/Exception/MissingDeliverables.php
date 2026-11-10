<?php

declare(strict_types=1);

namespace App\Domain\Beats\Exception;

use App\Domain\Shared\Exception\DomainException;

final class MissingDeliverables extends DomainException
{
    public function __construct()
    {
        parent::__construct('beats.missing_deliverables');
    }
}
