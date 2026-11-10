<?php

declare(strict_types=1);

namespace App\Domain\Identity\Exception;

use App\Domain\Shared\Exception\DomainException;

abstract class InvalidUser extends DomainException
{
}
