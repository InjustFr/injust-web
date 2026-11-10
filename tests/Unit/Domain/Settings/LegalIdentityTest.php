<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Settings;

use App\Domain\Settings\Exception\InvalidSiren;
use App\Domain\Settings\Exception\InvalidUrl;
use App\Domain\Settings\LegalIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegalIdentityTest extends TestCase
{
    public static function complete(string $siren = '443061841', string $phone = '+33 6 00 00 00 00', string $mediatorUrl = 'https://mediator.test'): LegalIdentity
    {
        return new LegalIdentity('Louis Injust', 'EI', $siren, '1 rue du Son, 75000 Paris', 'louis@injust.test', $phone, 'Louis Injust', 'Médiateur Test', $mediatorUrl, 'OVH SAS', '2 rue Kellermann, 59100 Roubaix');
    }

    public function testABlankIdentityIsAnIndividualBusinessWithNothingElse(): void
    {
        $identity = LegalIdentity::blank();

        self::assertSame(['EI', '', ''], [$identity->businessForm, $identity->name, $identity->siren]);
        self::assertFalse($identity->isComplete());
    }

    public function testAnIdentityWithEveryFieldIsComplete(): void
    {
        self::assertTrue(self::complete()->isComplete());
    }

    public function testAnyEmptyFieldMakesItIncomplete(): void
    {
        self::assertFalse(self::complete(phone: ' ')->isComplete());
        self::assertFalse(self::complete(siren: '')->isComplete());
    }

    public function testEveryFieldIsTrimmed(): void
    {
        $identity = new LegalIdentity(' N ', ' EI ', ' 443061841 ', ' A ', ' e@x.test ', ' P ', ' D ', ' M ', ' https://m.test ', ' H ', ' HA ');

        self::assertSame(['N', 'EI', '443061841', 'A', 'e@x.test', 'P', 'D', 'M', 'https://m.test', 'H', 'HA'], array_values(get_object_vars($identity)));
    }

    public function testTheSirenIsKeptWithoutSpaces(): void
    {
        self::assertSame('732829320', self::complete(siren: ' 732 829 320 ')->siren);
    }

    public function testANineThatIsNotDoubledCountsAsNine(): void
    {
        self::assertSame('900000001', self::complete(siren: '900000001')->siren);
    }

    /** @return iterable<array{string}> */
    public static function invalidSirens(): iterable
    {
        yield 'Luhn check fails' => ['443061842'];
        yield 'too short' => ['44306184'];
        yield 'too long' => ['4430618410'];
        yield 'letters' => ['44306184A'];
        yield 'a letter before nine digits' => ['X443061841'];
        yield 'a letter after nine digits' => ['443061841X'];
    }

    #[DataProvider('invalidSirens')]
    public function testAnInvalidSirenIsRefused(string $siren): void
    {
        try {
            self::complete(siren: $siren);
            self::fail('The SIREN was accepted.');
        } catch (InvalidSiren $refused) {
            self::assertSame(['settings.invalid_siren', ['siren' => $siren]], [$refused->getMessage(), $refused->parameters()]);
        }
    }

    public function testTheMediatorUrlMustBeHttps(): void
    {
        $this->expectException(InvalidUrl::class);

        self::complete(mediatorUrl: 'http://mediator.test');
    }
}
