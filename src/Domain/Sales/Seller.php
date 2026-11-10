<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Domain\Settings\LegalIdentity;

/**
 * @phpstan-type SellerData array{name: string, businessForm: string, siren: string, address: string, email: string, phone: string, vatNumber?: string}
 */
final readonly class Seller
{
    public function __construct(
        public string $name,
        public string $businessForm,
        public string $siren,
        public string $address,
        public string $email,
        public string $phone,
        public string $vatNumber = '',
    ) {
    }

    public static function of(LegalIdentity $identity): self
    {
        return new self($identity->name, $identity->businessForm, $identity->siren, $identity->address, $identity->email, $identity->phone, $identity->vatNumber);
    }

    /** @param SellerData $data */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['businessForm'], $data['siren'], $data['address'], $data['email'], $data['phone'], $data['vatNumber'] ?? '');
    }

    /** @return SellerData */
    public function toArray(): array
    {
        return ['name' => $this->name, 'businessForm' => $this->businessForm, 'siren' => $this->siren, 'address' => $this->address, 'email' => $this->email, 'phone' => $this->phone, 'vatNumber' => $this->vatNumber];
    }
}
