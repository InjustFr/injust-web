<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Webhooks;

use App\Application\Sales\SalesQueries;
use App\Presentation\Web\Webhooks\StripeSignature;
use App\Tests\Support\Json;
use App\Tests\Support\Payments\FakeStripe;
use App\Tests\Support\Payments\SellsOnline;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WebhookApiTest extends WebTestCase
{
    use SellsOnline;

    public function testACompletedCheckoutPaysTheOrderInvoicesItAndMailsTheBuyerOnce(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());

        self::notify($client, 'checkout.session.completed', $session);

        self::assertResponseStatusCodeSame(200);
        self::assertFalse($client->getResponse()->headers->has('Set-Cookie'));
        $paid = self::stored($order);
        self::assertSame(['paid', FakeStripe::BUYER, 'FR', 'FR', '2026-0001'], [$paid->status, $paid->customer?->name, $paid->customer?->country, $paid->customer?->cardCountry, $paid->invoiceNumber]);
        self::assertNotNull($paid->confirmationSentAt);
        self::assertSame('2026-0001', self::getContainer()->get(SalesQueries::class)->invoice($paid->id)?->number);
        self::assertCount(1, self::mails()->sent);
        self::assertSame($order->reference(), self::mails()->sent[0][0]->reference);
    }

    public function testAReplayedEventChangesNothing(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());
        self::notify($client, 'checkout.session.completed', $session);
        $first = self::stored($order);
        $sentAt = $first->confirmationSentAt?->getTimestamp();

        self::notify($client, 'checkout.session.completed', $session);
        self::notify($client, 'checkout.session.async_payment_succeeded', $session);

        self::assertResponseStatusCodeSame(200);
        $replayed = self::stored($order);
        self::assertSame(['paid', '2026-0001', $first->paymentIntentId, $sentAt], [$replayed->status, $replayed->invoiceNumber, $replayed->paymentIntentId, $replayed->confirmationSentAt?->getTimestamp()]);
        self::assertCount(1, self::mails()->sent);
    }

    public function testAnAmountThatDoesNotMatchIsRefusedAndLeavesTheOrderUnpaid(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId(), 100);

        self::notify($client, 'checkout.session.completed', $session);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString($order->reference(), Json::string(Json::decode((string) $client->getResponse()->getContent()), 'detail'));
        self::getContainer()->get(ManagerRegistry::class)->resetManager();
        $stored = self::stored($order);
        self::assertSame(['pending', null], [$stored->status, $stored->invoiceNumber]);
        self::assertSame([], self::mails()->sent);
    }

    public function testAFailedMailAnswers500SoStripeRetriesAndTheRetryOnlySendsTheMail(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());
        self::mails()->failures = 1;

        self::notify($client, 'checkout.session.completed', $session);

        self::assertResponseStatusCodeSame(500);
        $paid = self::stored($order);
        self::assertSame(['paid', '2026-0001', null], [$paid->status, $paid->invoiceNumber, $paid->confirmationSentAt]);
        self::assertSame([], self::mails()->sent);

        self::notify($client, 'checkout.session.completed', $session);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('2026-0001', self::stored($order)->invoiceNumber);
        self::assertNotNull(self::stored($order)->confirmationSentAt);
        self::assertCount(1, self::mails()->sent);
    }

    public function testACompletedButUnpaidCheckoutWaitsForTheAsyncPayment(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());

        self::notify($client, 'checkout.session.completed', ['payment_status' => 'unpaid'] + $session);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('pending', self::stored($order)->status);

        self::notify($client, 'checkout.session.async_payment_succeeded', $session);

        self::assertSame('paid', self::stored($order)->status);
    }

    public function testAnExpiredCheckoutExpiresThePendingOrder(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();

        self::notify($client, 'checkout.session.expired', ['id' => $order->checkoutSessionId(), 'object' => 'checkout.session', 'status' => 'expired']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('expired', self::stored($order)->status);
    }

    public function testAFullRefundMarksThePaidOrderRefunded(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());
        self::notify($client, 'checkout.session.completed', $session);
        $intent = self::stored($order)->paymentIntentId;

        self::notify($client, 'charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $intent, 'refunded' => false, 'amount_refunded' => 1000]);
        self::assertSame('paid', self::stored($order)->status);

        self::notify($client, 'charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $intent, 'refunded' => true, 'amount_refunded' => 4500]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('refunded', self::stored($order)->status);
    }

    public function testARefundBeforeThePaymentIsRecordedAnswers503SoStripeRetriesItAfterward(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());
        $refund = ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_'.$order->checkoutSessionId(), 'refunded' => true, 'amount_refunded' => 4500];

        self::notify($client, 'charge.refunded', $refund);

        self::assertResponseStatusCodeSame(503);
        self::assertSame('pending', self::stored($order)->status);

        self::notify($client, 'checkout.session.completed', $session);
        self::notify($client, 'charge.refunded', ['payment_intent' => self::stored($order)->paymentIntentId] + $refund);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('refunded', self::stored($order)->status);
    }

    public function testEventsForOtherSessionsOrOfOtherTypesAreAcknowledged(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();

        self::notify($client, 'checkout.session.completed', ['id' => 'cs_someone_else', 'payment_status' => 'paid']);
        self::assertResponseStatusCodeSame(200);
        self::notify($client, 'checkout.session.expired', ['id' => 'cs_someone_else']);
        self::assertResponseStatusCodeSame(200);
        self::notify($client, 'customer.created', ['id' => $order->checkoutSessionId()]);
        self::assertResponseStatusCodeSame(200);

        self::assertSame('pending', self::stored($order)->status);
    }

    public function testABadSignatureIsRefused(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $session = self::stripePays((string) $order->checkoutSessionId());

        self::notify($client, 'checkout.session.completed', $session, 't='.time().',v1='.str_repeat('0', 64));
        self::assertResponseStatusCodeSame(400);
        self::deliver($client, (string) json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => $session]]), null);
        self::assertResponseStatusCodeSame(400);

        self::assertSame('pending', self::stored($order)->status);
        self::assertSame([], self::mails()->sent);
    }

    public function testASignedPayloadThatIsNotAnEventIsRefused(): void
    {
        $client = self::shop();

        foreach (['not json', '{"type":"checkout.session.completed"}', '{"data":{"object":{}}}', '["checkout.session.completed"]'] as $payload) {
            self::deliver($client, $payload, self::getContainer()->get(StripeSignature::class)->sign($payload));
            self::assertResponseStatusCodeSame(400);
        }
    }
}
