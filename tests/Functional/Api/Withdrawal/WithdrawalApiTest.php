<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api\Withdrawal;

use App\Application\Sales\OrderView;
use App\Application\Sales\SalesMails;
use App\Application\Sales\SalesQueries;
use App\Domain\Sales\Order;
use App\Tests\Support\Json;
use App\Tests\Support\Sales\RecordingSalesMails;
use App\Tests\Support\Sales\SalesExamples;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

final class WithdrawalApiTest extends WebTestCase
{
    private static RecordingSalesMails $mails;

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
        parent::tearDown();
    }

    public function testThePreviewSummarisesTheOrderWithoutChangingIt(): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid());

        $client->jsonRequest('POST', '/api/withdrawal/preview', ['reference' => ' '.strtolower($order->reference()).' ', 'email' => ' STRIPE@example.test ']);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        self::assertSame([
            'reference' => $order->reference(),
            'productType' => 'lesson',
            'productLabel' => 'Cours de guitare',
            'amountCents' => 4500,
            'paidAt' => '2026-11-10T10:05:00+00:00',
            'lastDay' => '2026-11-24',
        ], Json::decode((string) $client->getResponse()->getContent()));
        self::assertSame('paid', self::view($order)->status);
        self::assertSame([], self::$mails->acknowledgements);
    }

    public function testTheWithdrawalIsRecordedThenAcknowledged(): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid(SalesExamples::production()));

        $client->jsonRequest('POST', '/api/withdrawal', ['reference' => $order->reference(), 'email' => 'stripe@example.test', 'name' => ' Joe Strummer ', 'reason' => 'Changed my mind.', 'locale' => 'fr']);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        self::assertSame(['reference' => $order->reference(), 'receivedAt' => '2026-11-12T10:00:00+00:00', 'acknowledged' => true], Json::decode((string) $client->getResponse()->getContent()));
        $view = self::view($order);
        self::assertSame('withdrawn', $view->status);
        self::assertNotNull($view->withdrawal);
        self::assertSame(['stripe@example.test', 'Joe Strummer', 'Changed my mind.', 'fr'], [$view->withdrawal->email, $view->withdrawal->name, $view->withdrawal->reason, $view->withdrawal->locale]);
        self::assertCount(1, self::$mails->acknowledgements);
        self::assertCount(1, self::$mails->notices);
    }

    public function testAFailedAcknowledgementStillRecordsTheWithdrawalAndSaysSo(): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid());
        self::$mails->broken = 'acknowledgement';

        $client->jsonRequest('POST', '/api/withdrawal', ['reference' => $order->reference(), 'email' => 'stripe@example.test', 'locale' => 'en']);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse(Json::at(Json::decode((string) $client->getResponse()->getContent()), 'acknowledged'));
        self::assertSame('withdrawn', self::view($order)->status);
    }

    #[DataProvider('endpoints')]
    public function testAfterTheFourteenthDayTheWithdrawalIsRefusedWithTheEndDate(string $endpoint): void
    {
        $client = self::visitor('2026-11-24 23:00:00 UTC');
        $order = self::store(SalesExamples::paid());

        $client->jsonRequest('POST', $endpoint, ['reference' => $order->reference(), 'email' => 'stripe@example.test', 'locale' => 'en']);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('The withdrawal period ended on November 24, 2026: contact me if you think this is a mistake.', self::detail($client));
        self::assertSame('paid', self::view($order)->status);
    }

    #[DataProvider('endpoints')]
    public function testABeatCannotBeWithdrawnOnline(string $endpoint): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid(SalesExamples::beat()));

        $client->jsonRequest('POST', $endpoint, ['reference' => $order->reference(), 'email' => 'stripe@example.test', 'locale' => 'en']);

        self::assertResponseStatusCodeSame(422);
        self::assertStringStartsWith('Order '.$order->reference().' cannot be withdrawn online', self::detail($client));
        self::assertSame('paid', self::view($order)->status);
    }

    #[DataProvider('mismatches')]
    public function testAnUnknownReferenceAndAWrongEmailGetTheSameGenericAnswer(string $endpoint, string $reference, string $email): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid());

        $client->jsonRequest('POST', $endpoint, ['reference' => $reference ?: $order->reference(), 'email' => $email, 'locale' => 'en']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('No order matches this reference and email address: check both in your order confirmation email.', self::detail($client));
        self::assertSame('paid', self::view($order)->status);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function mismatches(): iterable
    {
        foreach (self::endpoints() as $name => [$endpoint]) {
            yield $name.' unknown reference' => [$endpoint, 'INJ-ZZZZZZ', 'stripe@example.test'];
            yield $name.' wrong email' => [$endpoint, '', 'someone@example.test'];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function endpoints(): iterable
    {
        yield 'preview' => ['/api/withdrawal/preview'];
        yield 'submit' => ['/api/withdrawal'];
    }

    public function testAnInvalidRequestIsRefusedFieldByField(): void
    {
        $client = self::visitor();

        $client->jsonRequest('POST', '/api/withdrawal', ['reference' => ' ', 'email' => 'joe@', 'name' => str_repeat('a', 101), 'reason' => str_repeat('a', 2001), 'locale' => 'de']);

        self::assertResponseStatusCodeSame(422);
        $paths = array_map(static fn (mixed $violation): string => Json::string($violation, 'propertyPath'), Json::array(Json::decode((string) $client->getResponse()->getContent()), 'violations'));
        sort($paths);
        self::assertSame(['email', 'locale', 'name', 'reason', 'reference'], $paths);
    }

    public function testAVisitorGetsFiveAttemptsPerTenMinutesAcrossBothSteps(): void
    {
        $client = self::visitor();

        for ($i = 0; $i < 5; ++$i) {
            $client->jsonRequest('POST', 0 === $i % 2 ? '/api/withdrawal/preview' : '/api/withdrawal', ['reference' => 'INJ-ZZZZZZ', 'email' => 'joe@example.test', 'locale' => 'en'], ['REMOTE_ADDR' => '203.0.113.7']);
            self::assertResponseStatusCodeSame(422);
        }
        $client->jsonRequest('POST', '/api/withdrawal/preview', ['reference' => 'INJ-ZZZZZZ', 'email' => 'joe@example.test'], ['REMOTE_ADDR' => '203.0.113.7']);

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('You have made several withdrawal attempts in a short time: wait 10 minutes and try again.', self::detail($client));

        $client->jsonRequest('POST', '/api/withdrawal/preview', ['reference' => 'INJ-ZZZZZZ', 'email' => 'joe@example.test'], ['REMOTE_ADDR' => '203.0.113.8']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAnIpv6NetworkSharesOneBucket(): void
    {
        $client = self::visitor();
        $lookup = ['reference' => 'INJ-ZZZZZZ', 'email' => 'joe@example.test'];

        for ($i = 1; $i <= 5; ++$i) {
            $client->jsonRequest('POST', '/api/withdrawal/preview', $lookup, ['REMOTE_ADDR' => '2001:db8:1:2::'.$i]);
            self::assertResponseStatusCodeSame(422);
        }
        $client->jsonRequest('POST', '/api/withdrawal/preview', $lookup, ['REMOTE_ADDR' => '2001:db8:1:2:ffff:ffff:ffff:ffff']);
        self::assertResponseStatusCodeSame(429);

        $client->jsonRequest('POST', '/api/withdrawal/preview', $lookup, ['REMOTE_ADDR' => '2001:db8:1:3::1']);
        self::assertResponseStatusCodeSame(422);
    }

    #[DataProvider('endpoints')]
    public function testACrossOriginRequestIsRefused(string $endpoint): void
    {
        $client = self::visitor();
        $order = self::store(SalesExamples::paid());

        $client->jsonRequest('POST', $endpoint, ['reference' => $order->reference(), 'email' => 'stripe@example.test', 'locale' => 'en'], ['HTTP_ORIGIN' => 'https://evil.test']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('paid', self::view($order)->status);
    }

    private static function visitor(string $now = '2026-11-12 10:00:00 UTC'): KernelBrowser
    {
        Clock::set(new MockClock($now));
        $client = self::createClient();
        $client->disableReboot();
        self::getContainer()->get('cache.withdrawal_limiter')->clear();
        self::$mails = new RecordingSalesMails();
        self::getContainer()->set(SalesMails::class, self::$mails);

        return $client;
    }

    private static function store(Order $order): Order
    {
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->persist($order);
        $manager->flush();
        $manager->clear();

        return $order;
    }

    private static function view(Order $order): OrderView
    {
        $view = self::getContainer()->get(SalesQueries::class)->order($order->id()->toBase32());
        self::assertNotNull($view);

        return $view;
    }

    private static function detail(KernelBrowser $client): string
    {
        return Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail');
    }
}
