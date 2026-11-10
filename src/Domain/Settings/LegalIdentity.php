<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Settings\Exception\InvalidSiren;
use App\Domain\Settings\Exception\InvalidVatNumber;
use App\Domain\Shared\HttpsUrl;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final readonly class LegalIdentity
{
    public const int MAX_TEXT = 255;
    public const int MAX_BUSINESS_FORM = 20;
    public const int MAX_PHONE = 40;
    public const int MAX_VAT_NUMBER = 14;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $name;

    #[ORM\Column(length: self::MAX_BUSINESS_FORM)]
    public string $businessForm;

    #[ORM\Column(length: 9)]
    public string $siren;

    #[ORM\Column(type: 'text')]
    public string $address;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $email;

    #[ORM\Column(length: self::MAX_PHONE)]
    public string $phone;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $publicationDirector;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $mediatorName;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $mediatorUrl;

    #[ORM\Column(length: self::MAX_TEXT)]
    public string $hostName;

    #[ORM\Column(type: 'text')]
    public string $hostAddress;

    #[ORM\Column(length: self::MAX_PHONE)]
    public string $hostPhone;

    #[ORM\Column(length: self::MAX_VAT_NUMBER, options: ['default' => ''])]
    public string $vatNumber;

    public function __construct(
        string $name,
        string $businessForm,
        string $siren,
        string $address,
        string $email,
        string $phone,
        string $publicationDirector,
        string $mediatorName,
        string $mediatorUrl,
        string $hostName,
        string $hostAddress,
        string $hostPhone,
        string $vatNumber = '',
    ) {
        $this->name = trim($name);
        $this->businessForm = trim($businessForm);
        $this->siren = self::siren($siren);
        $this->address = trim($address);
        $this->email = trim($email);
        $this->phone = trim($phone);
        $this->publicationDirector = trim($publicationDirector);
        $this->mediatorName = trim($mediatorName);
        $this->mediatorUrl = HttpsUrl::orBlank($mediatorUrl);
        $this->hostName = trim($hostName);
        $this->hostAddress = trim($hostAddress);
        $this->hostPhone = trim($hostPhone);
        $this->vatNumber = self::vatNumber($vatNumber);
    }

    public static function blank(): self
    {
        return new self('', 'EI', '', '', '', '', '', '', '', '', '', '');
    }

    public function isComplete(): bool
    {
        return !\in_array('', array_diff_key(get_object_vars($this), ['vatNumber' => true]), true);
    }

    private static function vatNumber(string $vatNumber): string
    {
        $compact = strtoupper(preg_replace('/\s+/', '', $vatNumber) ?? '');
        if ('' !== $compact && 1 !== preg_match('/^[A-Z]{2}[A-Z0-9]{2,12}$/', $compact)) {
            throw new InvalidVatNumber($vatNumber);
        }

        return $compact;
    }

    private static function siren(string $siren): string
    {
        $siren = preg_replace('/\s+/', '', $siren) ?? '';
        if ('' !== $siren && (1 !== preg_match('/^\d{9}$/', $siren) || !self::passesLuhn($siren))) {
            throw new InvalidSiren($siren);
        }

        return $siren;
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        foreach (str_split($digits) as $position => $digit) {
            $value = (int) $digit * (1 + $position % 2);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return 0 === $sum % 10;
    }
}
