<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Payments;

use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutSession;
use App\Application\Sales\StripeRefund;
use App\Domain\Sales\Customer;
use App\Domain\Sales\PaymentDetails;
use App\Infrastructure\Payments\HttpStripeGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpStripeGatewayTest extends TestCase
{
    private const string ORDER = '01JCORDER00000000000000000';

    public function testACheckoutSessionIsOpenedWithTheOrderAsIdempotencyKey(): void
    {
        $seen = [];
        $form = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen, &$form): JsonMockResponse {
            $seen = [$method, $url, self::header($options, 'authorization'), self::header($options, 'idempotency-key'), self::header($options, 'content-type'), $options['timeout'] ?? null];
            $form = self::form($options);

            return new JsonMockResponse(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1', 'object' => 'checkout.session']);
        });

        $session = self::gateway($client)->startCheckout(self::request('buyer@example.test'));

        self::assertEquals(new CheckoutSession('cs_test_1', 'https://checkout.stripe.com/c/pay/cs_test_1'), $session);
        self::assertSame(['POST', 'https://api.stripe.com/v1/checkout/sessions', ['Authorization: Bearer sk_test_unit'], ['Idempotency-Key: '.self::ORDER], ['Content-Type: application/x-www-form-urlencoded'], 10.0], $seen);
        self::assertSame([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [['quantity' => '1', 'price_data' => ['currency' => 'eur', 'unit_amount' => '4500', 'product_data' => ['name' => 'Cours de guitare (1 h)']]]],
            'client_reference_id' => self::ORDER,
            'metadata' => ['order' => self::ORDER],
            'payment_intent_data' => ['metadata' => ['order' => self::ORDER]],
            'locale' => 'fr',
            'billing_address_collection' => 'required',
            'tax_id_collection' => ['enabled' => 'true'],
            'expires_at' => '1794313860',
            'success_url' => 'https://injust.test/fr/checkout/success?order='.self::ORDER,
            'cancel_url' => 'https://injust.test/fr/checkout/cancel?order='.self::ORDER,
            'customer_email' => 'buyer@example.test',
        ], $form);
    }

    public function testNoCustomerEmailIsSentWhenTheBuyerGaveNone(): void
    {
        $form = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$form): JsonMockResponse {
            $form = self::form($options);

            return new JsonMockResponse(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']);
        });

        self::gateway($client)->startCheckout(self::request(null));

        self::assertArrayNotHasKey('customer_email', $form);
    }

    /** @param list<MockResponse> $responses */
    #[DataProvider('brokenCheckouts')]
    public function testAFailedCheckoutIsARuntimeError(array $responses): void
    {
        $this->expectException(\RuntimeException::class);

        self::gateway(new MockHttpClient($responses))->startCheckout(self::request(null));
    }

    /** @return iterable<string, array{list<MockResponse>}> */
    public static function brokenCheckouts(): iterable
    {
        yield 'refused' => [[new JsonMockResponse(['error' => ['message' => 'Invalid API key']], ['http_code' => 401])]];
        yield 'down' => [[new MockResponse('', ['error' => 'offline'])]];
        yield 'not json' => [[new MockResponse('<html>oops</html>')]];
        yield 'no url' => [[new JsonMockResponse(['id' => 'cs_test_1'])]];
        yield 'no id' => [[new JsonMockResponse(['url' => 'https://checkout.stripe.com/c/pay/x'])]];
    }

    public function testThePaymentIsReadWithTheBuyerTheirBusinessAndTheirCardCountry(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): JsonMockResponse {
            $seen = [$method, $url, self::header($options, 'authorization'), $options['timeout']];

            return new JsonMockResponse(self::paidSession());
        });

        $details = self::gateway($client)->paymentDetails('cs_test_1');

        self::assertSame(['GET', 'https://api.stripe.com/v1/checkout/sessions/cs_test_1?expand[0]=payment_intent.latest_charge', ['Authorization: Bearer sk_test_unit'], 10.0], $seen);
        self::assertEquals(new PaymentDetails(
            'cs_test_1',
            'pi_test_1',
            4500,
            'eur',
            new Customer('Riot Buyer', 'riot@example.test', 'DE', 'Hauptstraße 1', 'Hinterhaus', '10115', 'Berlin', 'Berlin', 'Riot GmbH', 'DE123456789', 'US'),
            new \DateTimeImmutable('@1794312300'),
        ), $details);
    }

    public function testAConsumerPaymentHasNoBusinessAndMayHaveNoCard(): void
    {
        $session = [
            'customer_details' => ['email' => null, 'name' => null, 'business_name' => null, 'tax_ids' => [], 'address' => ['city' => 'Paris', 'country' => 'FR', 'line1' => '1 rue du Son', 'line2' => null, 'postal_code' => '75000', 'state' => null]],
            'payment_intent' => ['id' => 'pi_test_1', 'latest_charge' => ['created' => 1_794_312_300, 'payment_method_details' => ['type' => 'sepa_debit', 'sepa_debit' => ['country' => 'DE']]]],
        ] + self::paidSession();

        $details = self::gateway(new MockHttpClient([new JsonMockResponse($session)]))->paymentDetails('cs_test_1');

        self::assertEquals(new Customer('', '', 'FR', '1 rue du Son', '', '75000', 'Paris'), $details->customer);
    }

    public function testTheSessionIdIsEscapedInThePath(): void
    {
        $url = '';
        $client = new MockHttpClient(static function (string $method, string $requested) use (&$url): JsonMockResponse {
            $url = $requested;

            return new JsonMockResponse(self::paidSession());
        });

        self::gateway($client)->paymentDetails('cs/../x');

        self::assertStringStartsWith('https://api.stripe.com/v1/checkout/sessions/cs%2F..%2Fx?', $url);
    }

    /** @param array<string, mixed> $session */
    #[DataProvider('unusablePayments')]
    public function testAPaymentThatCannotBeReadIsARuntimeError(array $session, int $status = 200): void
    {
        $this->expectException(\RuntimeException::class);

        self::gateway(new MockHttpClient([new JsonMockResponse($session, ['http_code' => $status])]))->paymentDetails('cs_test_1');
    }

    /** @return iterable<string, array{0: array<string, mixed>, 1?: int}> */
    public static function unusablePayments(): iterable
    {
        $paid = self::paidSession();

        yield 'unknown session' => [['error' => ['message' => 'No such checkout.session']], 404];
        yield 'not paid yet' => [['payment_status' => 'unpaid'] + $paid];
        yield 'no payment status' => [array_diff_key($paid, ['payment_status' => true])];
        yield 'payment intent not expanded' => [['payment_intent' => 'pi_test_1'] + $paid];
        yield 'no amount' => [['amount_total' => null] + $paid];
        yield 'an amount that is not whole' => [['amount_total' => '4500'] + $paid];
        yield 'no currency' => [['currency' => null] + $paid];
        yield 'no billing country' => [['customer_details' => ['email' => 'riot@example.test', 'name' => 'Riot Buyer', 'address' => ['country' => null]]] + $paid];
        yield 'no charge time' => [['payment_intent' => ['id' => 'pi_test_1', 'latest_charge' => ['created' => null]]] + $paid];
    }

    public function testARefundIsAskedForThePaymentWithItsIdempotencyKeyAndReason(): void
    {
        $seen = [];
        $form = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen, &$form): JsonMockResponse {
            $seen = [$method, $url, self::header($options, 'authorization'), self::header($options, 'idempotency-key'), $options['timeout'] ?? null];
            $form = self::form($options);

            return new JsonMockResponse(['id' => 're_test_1', 'object' => 'refund', 'amount' => 1500, 'status' => 'succeeded', 'metadata' => ['reason' => 'Cours annulé']]);
        });

        $refund = self::gateway($client)->refund('pi_test_1', 1500, 'ORDER-0-1500', 'Cours annulé');

        self::assertEquals(new StripeRefund('re_test_1', 1500, 'succeeded', 'Cours annulé'), $refund);
        self::assertSame(['POST', 'https://api.stripe.com/v1/refunds', ['Authorization: Bearer sk_test_unit'], ['Idempotency-Key: ORDER-0-1500'], 10.0], $seen);
        self::assertSame(['payment_intent' => 'pi_test_1', 'amount' => '1500', 'reason' => 'requested_by_customer', 'metadata' => ['reason' => 'Cours annulé']], $form);
    }

    public function testARefundWithoutReasonSendsNoReasonMetadata(): void
    {
        $form = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$form): JsonMockResponse {
            $form = self::form($options);

            return new JsonMockResponse(['id' => 're_test_1', 'amount' => 4500, 'status' => 'pending', 'metadata' => []]);
        });

        $refund = self::gateway($client)->refund('pi_test_1', 4500, 'key', null);

        self::assertEquals(new StripeRefund('re_test_1', 4500, 'pending'), $refund);
        self::assertSame(['payment_intent' => 'pi_test_1', 'amount' => '4500', 'reason' => 'requested_by_customer'], $form);
    }

    /** @param list<MockResponse> $responses */
    #[DataProvider('brokenRefunds')]
    public function testAFailedRefundIsARuntimeError(array $responses): void
    {
        $this->expectException(\RuntimeException::class);

        self::gateway(new MockHttpClient($responses))->refund('pi_test_1', 4500, 'key', null);
    }

    /** @return iterable<string, array{list<MockResponse>}> */
    public static function brokenRefunds(): iterable
    {
        yield 'refused' => [[new JsonMockResponse(['error' => ['message' => 'Charge already refunded']], ['http_code' => 400])]];
        yield 'not json' => [[new MockResponse('<html>oops</html>')]];
        yield 'no id' => [[new JsonMockResponse(['amount' => 4500, 'status' => 'succeeded'])]];
        yield 'no amount' => [[new JsonMockResponse(['id' => 're_1', 'status' => 'succeeded'])]];
        yield 'no status' => [[new JsonMockResponse(['id' => 're_1', 'amount' => 4500])]];
    }

    public function testTheRefundsOfAPaymentAreListedOldestFirst(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): JsonMockResponse {
            $seen = [$method, $url, self::header($options, 'authorization'), $options['timeout'] ?? null];

            return new JsonMockResponse(['object' => 'list', 'has_more' => false, 'data' => [
                ['id' => 're_2', 'object' => 'refund', 'amount' => 3000, 'status' => 'failed', 'metadata' => []],
                ['id' => 're_1', 'object' => 'refund', 'amount' => 1500, 'status' => 'succeeded', 'metadata' => ['reason' => 'Retard']],
            ]]);
        });

        $refunds = self::gateway($client)->refunds('pi_test&x=1');

        self::assertSame(['GET', 'https://api.stripe.com/v1/refunds?payment_intent=pi_test%26x%3D1&limit=100', ['Authorization: Bearer sk_test_unit'], 10.0], $seen);
        self::assertEquals([new StripeRefund('re_1', 1500, 'succeeded', 'Retard'), new StripeRefund('re_2', 3000, 'failed')], $refunds);
    }

    public function testAPaymentWithoutRefundsHasAnEmptyList(): void
    {
        self::assertSame([], self::gateway(new MockHttpClient([new JsonMockResponse(['object' => 'list', 'data' => []])]))->refunds('pi_test_1'));
    }

    public function testRefundsSentAsAKeyedObjectStillComeBackAsAList(): void
    {
        $refunds = self::gateway(new MockHttpClient([new JsonMockResponse(['data' => ['first' => ['id' => 're_1', 'amount' => 100, 'status' => 'succeeded']]])]))->refunds('pi_test_1');

        self::assertSame([0], array_keys($refunds));
    }

    /** @param list<MockResponse> $responses */
    #[DataProvider('brokenRefundLists')]
    public function testARefundListThatCannotBeReadIsARuntimeError(array $responses): void
    {
        $this->expectException(\RuntimeException::class);

        self::gateway(new MockHttpClient($responses))->refunds('pi_test_1');
    }

    /** @return iterable<string, array{list<MockResponse>}> */
    public static function brokenRefundLists(): iterable
    {
        yield 'refused' => [[new JsonMockResponse(['error' => ['message' => 'No such payment_intent']], ['http_code' => 404])]];
        yield 'no data' => [[new JsonMockResponse(['object' => 'list'])]];
        yield 'a refund without id' => [[new JsonMockResponse(['data' => [['amount' => 1, 'status' => 'succeeded']]])]];
        yield 'a refund that is not an object' => [[new JsonMockResponse(['data' => ['re_1']])]];
    }

    /** @return array<string, mixed> */
    private static function paidSession(): array
    {
        return [
            'id' => 'cs_test_1',
            'object' => 'checkout.session',
            'amount_total' => 4500,
            'currency' => 'eur',
            'payment_status' => 'paid',
            'status' => 'complete',
            'customer_details' => [
                'email' => 'riot@example.test',
                'name' => 'Riot Buyer',
                'business_name' => 'Riot GmbH',
                'tax_ids' => [['type' => 'eu_vat', 'value' => 'DE123456789']],
                'address' => ['city' => 'Berlin', 'country' => 'DE', 'line1' => 'Hauptstraße 1', 'line2' => 'Hinterhaus', 'postal_code' => '10115', 'state' => 'Berlin'],
            ],
            'payment_intent' => [
                'id' => 'pi_test_1',
                'latest_charge' => [
                    'id' => 'ch_test_1',
                    'created' => 1_794_312_300,
                    'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'US', 'brand' => 'visa']],
                ],
            ],
        ];
    }

    private static function request(?string $email): CheckoutRequest
    {
        return new CheckoutRequest(
            self::ORDER,
            'INJ-000000',
            'Cours de guitare (1 h)',
            4500,
            'eur',
            'fr',
            $email,
            'https://injust.test/fr/checkout/success?order='.self::ORDER,
            'https://injust.test/fr/checkout/cancel?order='.self::ORDER,
        );
    }

    private static function gateway(MockHttpClient $client): HttpStripeGateway
    {
        return new HttpStripeGateway($client, new MockClock('@1794312000'), 'sk_test_unit');
    }

    /**
     * @param array<mixed> $options
     *
     * @return list<string>
     */
    private static function header(array $options, string $name): array
    {
        $headers = $options['normalized_headers'] ?? [];
        self::assertIsArray($headers);
        $values = $headers[$name] ?? [];
        self::assertIsArray($values);

        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * @param array<mixed> $options
     *
     * @return array<mixed>
     */
    private static function form(array $options): array
    {
        self::assertIsString($options['body']);
        parse_str($options['body'], $form);

        return $form;
    }
}
