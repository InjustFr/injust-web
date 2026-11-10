<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Web\Webhooks;

use App\Presentation\Web\Webhooks\StripeSignature;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class StripeSignatureTest extends TestCase
{
    private const string SECRET = 'whsec_unit';
    private const string BODY = '{"id":"evt_1","type":"checkout.session.completed"}';
    private const int NOW = 1_794_312_000;

    public function testAHeaderSignedWithTheSecretIsAccepted(): void
    {
        self::assertTrue(self::signature()->verify(self::BODY, self::header(self::NOW)));
    }

    public function testItSignsTheWayStripeDoes(): void
    {
        self::assertSame(self::header(self::NOW), self::signature()->sign(self::BODY));
        self::assertTrue(self::signature()->verify(self::BODY, self::signature()->sign(self::BODY)));
    }

    public function testATamperedBodyIsRefused(): void
    {
        self::assertFalse(self::signature()->verify(str_replace('evt_1', 'evt_2', self::BODY), self::header(self::NOW)));
    }

    public function testAnotherSecretIsRefused(): void
    {
        self::assertFalse(self::signature()->verify(self::BODY, self::header(self::NOW, 'whsec_other')));
    }

    public function testAnEmptySecretRefusesEverything(): void
    {
        $signature = new StripeSignature('', new MockClock('@'.self::NOW));

        self::assertFalse($signature->verify(self::BODY, self::header(self::NOW, '')));
    }

    public function testTheTimestampMayDriftFiveMinutesEitherWay(): void
    {
        self::assertTrue(self::signature()->verify(self::BODY, self::header(self::NOW - 300)));
        self::assertTrue(self::signature()->verify(self::BODY, self::header(self::NOW + 300)));
        self::assertFalse(self::signature()->verify(self::BODY, self::header(self::NOW - 301)));
        self::assertFalse(self::signature()->verify(self::BODY, self::header(self::NOW + 301)));
    }

    public function testAnyOfSeveralSignaturesMayMatch(): void
    {
        $valid = substr(self::header(self::NOW), \strlen('t='.self::NOW.',v1='));

        self::assertTrue(self::signature()->verify(self::BODY, 't='.self::NOW.',v1='.str_repeat('0', 64).',v0=legacy,v1='.$valid));
        self::assertTrue(self::signature()->verify(self::BODY, 'v1='.$valid.', t='.self::NOW));
    }

    #[DataProvider('malformedHeaders')]
    public function testAMalformedHeaderIsRefused(?string $header): void
    {
        self::assertFalse(self::signature()->verify(self::BODY, $header));
    }

    /** @return iterable<string, array{?string}> */
    public static function malformedHeaders(): iterable
    {
        $valid = hash_hmac('sha256', self::NOW.'.'.self::BODY, self::SECRET);

        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'no timestamp' => ['v1='.$valid];
        yield 'no signature' => ['t='.self::NOW];
        yield 'only a v0 signature' => ['t='.self::NOW.',v0='.$valid];
        yield 'a timestamp that is not a number' => ['t=12abc,v1='.$valid];
        yield 'a signed timestamp' => ['t=+'.self::NOW.',v1='.$valid];
        yield 'a signature with trailing junk' => ['t='.self::NOW.',v1='.$valid.'=junk'];
        yield 'garbage' => ['nonsense'];
    }

    private static function signature(): StripeSignature
    {
        return new StripeSignature(self::SECRET, new MockClock('@'.self::NOW));
    }

    private static function header(int $timestamp, string $secret = self::SECRET): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.self::BODY, $secret);
    }
}
