<?php

declare(strict_types=1);

namespace App\Tests\Support\Payments;

use App\Infrastructure\Payments\StripeObject;
use App\Presentation\Web\Webhooks\StripeSignature;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FakeStripe implements HttpClientInterface
{
    use DecoratorTrait;

    public const string BUYER = 'Test Buyer';

    private const string URL = 'https://api.stripe.com/v1/';
    private const string ID = '[A-Za-z0-9_]+';

    public function __construct(
        HttpClientInterface $client,
        private readonly CacheItemPoolInterface $sessions,
        private readonly ClockInterface $clock,
        private readonly HttpKernelInterface $kernel,
        private readonly StripeSignature $signature,
    ) {
        $this->client = $client;
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!str_starts_with($url, self::URL)) {
            return $this->client->request($method, $url, $options);
        }

        return new MockHttpClient($this->answer(...))->request($method, $url, $options);
    }

    /** @param array<string, mixed> $options */
    private function answer(string $method, string $url, array $options): JsonMockResponse
    {
        $route = $method.' '.substr((string) parse_url($url, \PHP_URL_PATH), \strlen('/v1/'));
        $form = self::form($options);

        return match (true) {
            'POST checkout/sessions' === $route => $this->open($form),
            1 === preg_match('{^GET checkout/sessions/('.self::ID.')$}', $route, $match) => $this->show($match[1]),
            'POST refunds' === $route => $this->refund($form),
            'GET refunds' === $route => new JsonMockResponse(['object' => 'list', 'has_more' => false, 'data' => $this->refundsOf(self::query($url, 'payment_intent'))]),
            1 === preg_match('{^POST test_helpers/checkout/sessions/('.self::ID.')/pay$}', $route, $match) => $this->pay($match[1], $form),
            default => self::missing(),
        };
    }

    private function open(StripeObject $form): JsonMockResponse
    {
        $id = 'cs_test_'.bin2hex(random_bytes(12));
        $amount = (int) $form->optional('line_items.0.price_data.unit_amount');
        $success = (string) $form->optional('success_url');

        return new JsonMockResponse($this->save([
            'id' => $id,
            'object' => 'checkout.session',
            'url' => Request::create($success)->getSchemeAndHttpHost().'/_fake-checkout/'.$id,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'amount_total' => $amount,
            'currency' => $form->optional('line_items.0.price_data.currency'),
            'client_reference_id' => $form->optional('client_reference_id'),
            'metadata' => ['order' => $form->optional('metadata.order')],
            'customer_email' => $form->optional('customer_email'),
            'locale' => $form->optional('locale'),
            'expires_at' => (int) $form->optional('expires_at'),
            'success_url' => $success,
            'cancel_url' => $form->optional('cancel_url'),
            'customer_details' => null,
            'payment_intent' => null,
        ]));
    }

    private function show(string $id): JsonMockResponse
    {
        $session = $this->find($id);

        return null === $session ? self::missing() : new JsonMockResponse($session);
    }

    private function pay(string $id, StripeObject $form): JsonMockResponse
    {
        $session = $this->find($id);
        if (null === $session) {
            return self::missing();
        }
        $suffix = substr($id, \strlen('cs_test_'));
        $amount = $form->optional('amount_total');

        return new JsonMockResponse($this->save([
            'status' => 'complete',
            'payment_status' => 'paid',
            'amount_total' => null === $amount ? $session['amount_total'] : (int) $amount,
            'customer_details' => [
                'email' => $session['customer_email'] ?? 'test.buyer@example.test',
                'name' => self::BUYER,
                'business_name' => null,
                'tax_ids' => [],
                'address' => ['line1' => '1 rue du Test', 'line2' => null, 'postal_code' => '75001', 'city' => 'Paris', 'state' => null, 'country' => 'FR'],
            ],
            'payment_intent' => [
                'id' => 'pi_test_'.$suffix,
                'latest_charge' => ['id' => 'ch_test_'.$suffix, 'created' => $this->clock->now()->getTimestamp(), 'payment_method_details' => ['type' => 'card', 'card' => ['country' => 'FR', 'brand' => 'visa', 'last4' => '4242']]],
            ],
        ] + $session));
    }

    private function refund(StripeObject $form): JsonMockResponse
    {
        $intent = (string) $form->optional('payment_intent');
        $refund = [
            'id' => 're_test_'.bin2hex(random_bytes(12)),
            'object' => 'refund',
            'amount' => (int) $form->optional('amount'),
            'currency' => 'eur',
            'payment_intent' => $intent,
            'reason' => $form->optional('reason'),
            'status' => 'succeeded',
            'metadata' => array_filter(['reason' => $form->optional('metadata.reason')]),
            'created' => $this->clock->now()->getTimestamp(),
        ];
        $refunds = [$refund, ...$this->refundsOf($intent)];
        $this->sessions->save($this->sessions->getItem('refunds_'.$intent)->set($refunds));
        $this->dispatchRefunded($intent, $refunds);

        return new JsonMockResponse($refund);
    }

    /** @return list<mixed> */
    private function refundsOf(string $intent): array
    {
        $refunds = $this->sessions->getItem('refunds_'.$intent)->get();

        return \is_array($refunds) ? array_values($refunds) : [];
    }

    /** @param list<mixed> $refunds */
    private function dispatchRefunded(string $intent, array $refunds): void
    {
        $event = json_encode(['id' => 'evt_test_'.bin2hex(random_bytes(8)), 'object' => 'event', 'type' => 'charge.refunded', 'data' => ['object' => [
            'object' => 'charge',
            'payment_intent' => $intent,
            'amount_refunded' => array_sum(array_column($refunds, 'amount')),
            'refunds' => ['object' => 'list', 'data' => $refunds],
        ]]], \JSON_THROW_ON_ERROR);
        $this->kernel->handle(Request::create('/webhooks/stripe', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $this->signature->sign($event)], content: $event), HttpKernelInterface::SUB_REQUEST);
    }

    private static function query(string $url, string $key): string
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $value = $query[$key] ?? '';

        return \is_string($value) ? $value : '';
    }

    /** @return ?array<mixed> */
    private function find(string $id): ?array
    {
        $session = $this->sessions->getItem($id)->get();

        return \is_array($session) ? $session : null;
    }

    /**
     * @param array<mixed> $session
     *
     * @return array<mixed>
     */
    private function save(array $session): array
    {
        $id = $session['id'];
        \assert(\is_string($id));
        $this->sessions->save($this->sessions->getItem($id)->set($session));

        return $session;
    }

    /** @param array<string, mixed> $options */
    private static function form(array $options): StripeObject
    {
        $body = $options['body'] ?? '';
        parse_str(\is_string($body) ? $body : '', $form);

        return new StripeObject($form);
    }

    private static function missing(): JsonMockResponse
    {
        return new JsonMockResponse(['error' => ['type' => 'invalid_request_error', 'message' => 'No such checkout.session']], ['http_code' => 404]);
    }
}
