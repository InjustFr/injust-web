<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

final class NotFound extends DomainException
{
    public function __construct(string $subject, string $id)
    {
        parent::__construct('shared.not_found', ['subject' => $subject, 'id' => $id]);
    }
}
