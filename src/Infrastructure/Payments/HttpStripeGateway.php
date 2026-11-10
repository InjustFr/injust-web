<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Application\Sales\CheckoutRequest;
use App\Application\Sales\CheckoutSession;
use App\Application\Sales\PaymentGateway;
use App\Domain\Sales\Customer;
use App\Domain\Sales\PaymentDetails;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpStripeGateway implements PaymentGateway
{
    private const string API = 'https://api.stripe.com/v1/checkout/sessions';
    private const int SESSION_LIFETIME = 1860;
    private const float TIMEOUT = 10.0;

    public function __construct(
        private HttpClientInterface $client,
        private ClockInterface $clock,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private string $secretKey,
    ) {
    }

    public function startCheckout(CheckoutRequest $request): CheckoutSession
    {
        $session = $this->call('POST', self::API, ['Idempotency-Key' => $request->orderId], [
            'body' => [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [['quantity' => 1, 'price_data' => ['currency' => $request->currency, 'unit_amount' => $request->amountCents, 'product_data' => ['name' => $request->label]]]],
                'client_reference_id' => $request->orderId,
                'metadata' => ['order' => $request->orderId],
                'payment_intent_data' => ['metadata' => ['order' => $request->orderId]],
                'locale' => $request->locale,
                'billing_address_collection' => 'required',
                'tax_id_collection' => ['enabled' => 'true'],
                'expires_at' => $this->clock->now()->getTimestamp() + self::SESSION_LIFETIME,
                'success_url' => $request->successUrl,
                'cancel_url' => $request->cancelUrl,
            ] + (null === $request->email ? [] : ['customer_email' => $request->email]),
        ]);

        return new CheckoutSession($session->string('id'), $session->string('url'));
    }

    public function paymentDetails(string $sessionId): PaymentDetails
    {
        $session = $this->call('GET', self::API.'/'.rawurlencode($sessionId), [], ['query' => ['expand' => ['payment_intent.latest_charge']]]);
        if ('paid' !== $session->string('payment_status')) {
            throw new \UnexpectedValueException(\sprintf('Checkout session %s is not paid.', $sessionId));
        }

        return new PaymentDetails(
            $sessionId,
            $session->string('payment_intent.id'),
            $session->int('amount_total'),
            $session->string('currency'),
            self::customer($session),
            new \DateTimeImmutable('@'.$session->int('payment_intent.latest_charge.created')),
        );
    }

    private static function customer(StripeObject $session): Customer
    {
        return new Customer(
            $session->optional('customer_details.name') ?? '',
            $session->optional('customer_details.email') ?? '',
            $session->string('customer_details.address.country'),
            $session->optional('customer_details.address.line1') ?? '',
            $session->optional('customer_details.address.line2') ?? '',
            $session->optional('customer_details.address.postal_code') ?? '',
            $session->optional('customer_details.address.city') ?? '',
            $session->optional('customer_details.address.state'),
            $session->optional('customer_details.business_name'),
            $session->optional('customer_details.tax_ids.0.value'),
            $session->optional('payment_intent.latest_charge.payment_method_details.card.country'),
        );
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $options
     */
    private function call(string $method, string $url, array $headers, array $options): StripeObject
    {
        try {
            return new StripeObject($this->client->request($method, $url, ['headers' => ['Authorization' => 'Bearer '.$this->secretKey] + $headers, 'timeout' => self::TIMEOUT] + $options)->toArray());
        } catch (DecodingExceptionInterface $exception) {
            throw new \UnexpectedValueException('Stripe answered something that is not JSON.', previous: $exception);
        }
    }
}
