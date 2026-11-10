<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Checkout;

use App\Domain\Production\Pricing;
use App\Tests\Support\Json;
use App\Tests\Support\Payments\SellsOnline;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CheckoutApiTest extends WebTestCase
{
    use SellsOnline;

    public function testALessonIsOrderedThenTheBuyerIsSentToThePaymentPage(): void
    {
        $client = self::shop();
        self::openShop();
        $lesson = self::lesson();

        self::checkout($client, $lesson);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        $order = self::onlyOrder();
        self::assertSame(['url' => 'https://injust.test/_fake-checkout/'.$order->checkoutSessionId], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame(['pending', 'lesson', $lesson, 'Cours de guitare', 4500, 'fr', 'buyer@example.test', 'online'], [$order->status, $order->productType, $order->productId, $order->productLabel, $order->price->cents, $order->locale, $order->email, $order->productFormat]);
        self::assertSame(['terms', 'early-start', 'pro-rata'], array_map(static fn ($consent): string => $consent->kind->value, $order->consents));
        self::assertSame('fr:terms:v1', $order->consents[0]->text);
    }

    public function testAFixedPriceServiceIsOrderedInEnglishWithoutAnEmail(): void
    {
        $client = self::shop();
        self::openShop();

        self::checkout($client, self::service(), ['productType' => 'production', 'locale' => 'en', 'email' => '']);

        self::assertResponseStatusCodeSame(201);
        $order = self::onlyOrder();
        self::assertSame(['production', 'Mixing', 12000, 'en', ''], [$order->productType, $order->productLabel, $order->price->cents, $order->locale, $order->email]);
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('refusedOrders')]
    public function testAnOrderAgainstTheRulesIsRefused(bool $checkout, bool $lessons, string $product, array $overrides, string $detail): void
    {
        $client = self::shop();
        self::openShop($checkout, $lessons, beats: 'beat' === ($overrides['productType'] ?? null));
        $ids = ['lesson' => self::lesson(), 'free' => self::lesson(0), 'quote' => self::service(Pricing::Quote), 'unknown' => '01JCUNKNOWN000000000000000', 'none' => ''];

        self::checkout($client, $ids[$product], ['productType' => 'quote' === $product ? 'production' : 'lesson', ...$overrides]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame($detail, Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::assertSame([], self::orders());
    }

    /** @return iterable<string, array{bool, bool, string, array<string, mixed>, string}> */
    public static function refusedOrders(): iterable
    {
        yield 'checkout switched off' => [false, true, 'lesson', [], 'Online payment is not open yet: contact me to book.'];
        yield 'section switched off' => [true, false, 'lesson', [], 'This part of the site is closed for now: contact me to book.'];
        yield 'quote service' => [true, true, 'quote', [], 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.'];
        yield 'free lesson' => [true, true, 'free', [], 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.'];
        yield 'no lesson' => [true, true, 'none', [], 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.'];
        yield 'unknown lesson' => [true, true, 'unknown', [], 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.'];
        yield 'beat' => [true, true, 'lesson', ['productType' => 'beat', 'consents' => ['terms', 'digital-waiver', 'licence']], 'This offer cannot be paid online: it may have changed or need a quote. Contact me to book it.'];
        yield 'missing consent' => [true, true, 'lesson', ['consents' => ['terms', 'early-start', 'licence']], 'Tick every box above to place the order.'];
        yield 'terms edited since the page was shown' => [true, true, 'lesson', ['cgvVersion' => 2], 'The terms of sale changed while this page was open: reload it, read them and order again.'];
    }

    public function testAnInvalidPayloadIsRefusedFieldByField(): void
    {
        $client = self::shop();
        self::openShop();

        $client->jsonRequest('POST', '/api/checkout', ['productType' => 'lesson', 'productId' => self::lesson(), 'email' => 'buyer@', 'locale' => 'de', 'consents' => self::LESSON_CONSENTS]);

        self::assertResponseStatusCodeSame(422);
        $paths = array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'));
        sort($paths);
        self::assertSame(['email', 'locale'], $paths);
        self::assertSame([], self::orders());
    }

    public function testAnUnknownProductTypeIsRefused(): void
    {
        $client = self::shop();
        self::openShop();

        self::checkout($client, self::lesson(), ['productType' => 'merch']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], self::orders());
    }

    public function testAVisitorStartsTenCheckoutsPerTenMinutes(): void
    {
        $client = self::shop();
        self::openShop(checkout: false);
        $lesson = self::lesson();

        for ($i = 0; $i < 10; ++$i) {
            self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '203.0.113.7']);
            self::assertResponseStatusCodeSame(422);
        }
        self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertStringContainsString('10 minutes', Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));

        self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '203.0.113.8']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnIpv6NetworkSharesOneBucket(): void
    {
        $client = self::shop();
        self::openShop(checkout: false);
        $lesson = self::lesson();

        for ($i = 1; $i <= 10; ++$i) {
            self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '2001:db8:1:2::'.$i]);
            self::assertResponseStatusCodeSame(422);
        }
        self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '2001:db8:1:2:ffff:ffff:ffff:ffff']);
        self::assertResponseStatusCodeSame(429);

        self::checkout($client, $lesson, server: ['REMOTE_ADDR' => '2001:db8:1:3::1']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTheEmailIsStoredTrimmed(): void
    {
        $client = self::shop();
        self::openShop();

        self::checkout($client, self::lesson(), ['email' => '  buyer@example.test ']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('buyer@example.test', self::onlyOrder()->email);
    }

    public function testACrossOriginOrderIsRefused(): void
    {
        $client = self::shop();
        self::openShop();

        self::checkout($client, self::lesson(), server: ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], self::orders());
    }
}
