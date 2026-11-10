<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Customer;
use App\Domain\Sales\VatCase;
use App\Domain\Settings\VatMentions;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VatCaseTest extends TestCase
{
    /** @return iterable<string, array{string, ?string, ?string, VatCase}> */
    public static function buyers(): iterable
    {
        yield 'French consumer' => ['FR', null, null, VatCase::Consumer];
        yield 'French business: the franchise covers every French sale' => ['FR', 'FR12345678901', 'Riot SAS', VatCase::Consumer];
        yield 'EU business with a VAT number' => ['DE', 'DE123456789', 'Riot GmbH', VatCase::EuBusiness];
        yield 'EU business known by its VAT number only' => ['BE', 'BE0123456789', null, VatCase::EuBusiness];
        yield 'EU company without a VAT number is treated as a consumer' => ['IT', null, 'Riot SRL', VatCase::Consumer];
        yield 'EU consumer' => ['ES', null, null, VatCase::Consumer];
        yield 'lower-case EU country' => ['de', 'DE123456789', null, VatCase::EuBusiness];
        yield 'Monaco is outside the EU list' => ['MC', 'MC12345', null, VatCase::NonEuBusiness];
        yield 'non-EU company' => ['US', null, 'Riot Inc', VatCase::NonEuBusiness];
        yield 'non-EU tax id' => ['GB', 'GB123456789', null, VatCase::NonEuBusiness];
        yield 'non-EU consumer' => ['CH', null, null, VatCase::Consumer];
        yield 'blank company and VAT id' => ['US', ' ', ' ', VatCase::Consumer];
    }

    #[DataProvider('buyers')]
    public function testTheCaseFollowsTheBuyerCountryAndBusiness(string $country, ?string $vatId, ?string $company, VatCase $case): void
    {
        self::assertSame($case, VatCase::of(SalesExamples::customer($country, $vatId, $company)));
    }

    public function testTheMentionComesFromTheSettings(): void
    {
        $mentions = new VatMentions('Franchise', 'Autoliquidation', 'Hors UE');

        self::assertSame(['Franchise', 'Autoliquidation', 'Hors UE'], array_map(static fn (VatCase $case): string => $case->mention($mentions), [VatCase::Consumer, VatCase::EuBusiness, VatCase::NonEuBusiness]));
    }

    public function testEveryEuMemberStateButFranceIsCrossBorder(): void
    {
        $crossBorder = array_filter(['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'GB', 'CH', 'NO', 'US'], static fn (string $country): bool => VatCase::isEuConsumerAbroad(SalesExamples::customer($country)));

        self::assertSame(['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'], array_values($crossBorder));
    }

    public function testAnEuBusinessIsNotAConsumerAbroad(): void
    {
        self::assertFalse(VatCase::isEuConsumerAbroad(SalesExamples::customer('DE', 'DE123456789')));
    }

    public function testTheCustomerIsNormalised(): void
    {
        $customer = new Customer(' Test Buyer ', ' a@example.test ', ' be ', ' 1 rue ', ' ', ' 1000 ', ' Bruxelles ', ' ', ' ', ' be0123 ', ' be ');

        self::assertSame(['Test Buyer', 'a@example.test', 'BE', '1 rue', '', '1000', 'Bruxelles', null, null, 'BE0123', 'BE'], [$customer->name, $customer->email, $customer->country, $customer->line1, $customer->line2, $customer->postalCode, $customer->city, $customer->state, $customer->company, $customer->vatId, $customer->cardCountry]);
        self::assertEquals($customer, Customer::fromArray($customer->toArray()));
    }
}
