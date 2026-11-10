<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Settings\VatMentions;

enum VatCase: string
{
    case Consumer = 'consumer';
    case EuBusiness = 'eu-business';
    case NonEuBusiness = 'non-eu-business';

    public const string HOME = 'FR';
    public const array EU = ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'];

    public static function of(Customer $customer): self
    {
        if (self::HOME === $customer->country) {
            return self::Consumer;
        }
        if (\in_array($customer->country, self::EU, true)) {
            return null === $customer->vatId ? self::Consumer : self::EuBusiness;
        }

        return null === $customer->vatId && null === $customer->company ? self::Consumer : self::NonEuBusiness;
    }

    public static function isEuConsumerAbroad(Customer $customer): bool
    {
        return self::HOME !== $customer->country && \in_array($customer->country, self::EU, true) && self::Consumer === self::of($customer);
    }

    public function mention(VatMentions $mentions): string
    {
        return match ($this) {
            self::Consumer => $mentions->consumer,
            self::EuBusiness => $mentions->euBusiness,
            self::NonEuBusiness => $mentions->nonEuBusiness,
        };
    }
}
