<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exception;

abstract class DomainException extends \DomainException
{
    /**
     * @param array<string, string|int|float|\DateTimeInterface> $parameters
     */
    public function __construct(string $messageKey, private readonly array $parameters = [])
    {
        parent::__construct($messageKey);
    }

    /**
     * @return array<string, string|int|float|\DateTimeInterface>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }
}
