<?php

declare(strict_types=1);

namespace App\Domain\Sales;

/**
 * @phpstan-type CustomerData array{name: string, email: string, country: string, line1: string, line2: string, postalCode: string, city: string, state: ?string, company: ?string, vatId: ?string, cardCountry: ?string}
 */
final readonly class Customer
{
    public string $name;
    public string $email;
    public string $country;
    public string $line1;
    public string $line2;
    public string $postalCode;
    public string $city;
    public ?string $state;
    public ?string $company;
    public ?string $vatId;
    public ?string $cardCountry;

    public function __construct(
        string $name,
        string $email,
        string $country,
        string $line1,
        string $line2,
        string $postalCode,
        string $city,
        ?string $state = null,
        ?string $company = null,
        ?string $vatId = null,
        ?string $cardCountry = null,
    ) {
        $this->name = trim($name);
        $this->email = trim($email);
        $this->country = strtoupper(trim($country));
        $this->line1 = trim($line1);
        $this->line2 = trim($line2);
        $this->postalCode = trim($postalCode);
        $this->city = trim($city);
        $this->state = self::optional($state);
        $this->company = self::optional($company);
        $this->vatId = self::optional(strtoupper($vatId ?? ''));
        $this->cardCountry = self::optional(strtoupper($cardCountry ?? ''));
    }

    /** @param CustomerData $data */
    public static function fromArray(array $data): self
    {
        return new self($data['name'], $data['email'], $data['country'], $data['line1'], $data['line2'], $data['postalCode'], $data['city'], $data['state'], $data['company'], $data['vatId'], $data['cardCountry']);
    }

    /** @return CustomerData */
    public function toArray(): array
    {
        return ['name' => $this->name, 'email' => $this->email, 'country' => $this->country, 'line1' => $this->line1, 'line2' => $this->line2, 'postalCode' => $this->postalCode, 'city' => $this->city, 'state' => $this->state, 'company' => $this->company, 'vatId' => $this->vatId, 'cardCountry' => $this->cardCountry];
    }

    private static function optional(?string $value): ?string
    {
        $value = trim($value ?? '');

        return '' === $value ? null : $value;
    }
}
