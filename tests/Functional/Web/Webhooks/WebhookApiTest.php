<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web\Webhooks;

use App\Application\Sales\CreditNoteView;
use App\Application\Sales\OrderView;
use App\Application\Sales\RefundOrder\RefundOrder;
use App\Application\Sales\RefundOrder\RefundOrderHandler;
use App\Application\Sales\SalesQueries;
use App\Domain\Sales\Order;
use App\Presentation\Web\Webhooks\StripeSignature;
use App\Tests\Support\Json;
use App\Tests\Support\Payments\FakeStripe;
use App\Tests\Support\Payments\SellsOnline;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

    public function testARefundFromTheAdminIssuesACreditNoteOnTheInvoiceSequenceAndMailsIt(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);

        $refundId = self::getContainer()->get(RefundOrderHandler::class)(new RefundOrder($order->id(), 4500, 'Cours annulé'));

        $refunded = self::stored($order);
        self::assertSame(['refunded', 4500], [$refunded->status, $refunded->refundedCents]);
        self::assertSame([['2026-0002', 4500, 'Cours annulé', $refundId, '2026-0001']], self::creditNotes($refunded));
        self::assertCount(1, self::mails()->creditNotes);
        self::assertSame('2026-0002', self::mails()->creditNotes[0][1]->number);
        self::assertNotNull(self::getContainer()->get(SalesQueries::class)->creditNote($refunded->creditNotes[0]->id)?->mailedAt);
    }

    public function testTwoPartialRefundsIssueTwoCreditNotesAndTheSecondOneCompletesTheRefund(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);
        $intent = (string) self::stored($order)->paymentIntentId;

        self::stripeRefunds($intent, 1000);

        $partly = self::stored($order);
        self::assertSame(['paid', 1000], [$partly->status, $partly->refundedCents]);

        self::stripeRefunds($intent, 3500);

        $refunded = self::stored($order);
        self::assertSame(['refunded', 4500], [$refunded->status, $refunded->refundedCents]);
        self::assertSame(['2026-0002', '2026-0003'], array_column(self::creditNotes($refunded), 0));
        self::assertSame([1000, 3500], array_column(self::creditNotes($refunded), 1));
        self::assertCount(2, self::mails()->creditNotes);
    }

    public function testAReplayedRefundEventChangesNothing(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);
        $intent = (string) self::stored($order)->paymentIntentId;
        self::stripeRefunds($intent, 1000);

        self::notify($client, 'charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $intent, 'refunded' => false, 'amount_refunded' => 1000]);

        self::assertResponseStatusCodeSame(200);
        $replayed = self::stored($order);
        self::assertSame(['paid', 1000], [$replayed->status, $replayed->refundedCents]);
        self::assertCount(1, $replayed->creditNotes);
        self::assertCount(1, self::mails()->creditNotes);
    }

    public function testARefundMadeInTheStripeDashboardIssuesACreditNoteWithItsReason(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);
        $intent = (string) self::stored($order)->paymentIntentId;

        $refund = self::stripeRefunds($intent, 2000, 'Geste commercial');

        $stored = self::stored($order);
        self::assertSame(['paid', 2000], [$stored->status, $stored->refundedCents]);
        self::assertSame([['2026-0002', 2000, 'Geste commercial', $refund, '2026-0001']], self::creditNotes($stored));
    }

    public function testARefundBeforeThePaymentIsRecordedAnswers503SoStripeRetriesItAfterward(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::pendingOrder();
        $intent = 'pi_test_'.substr((string) $order->checkoutSessionId(), \strlen('cs_test_'));
        self::stripeRefunds($intent, 4500);
        $refund = ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $intent, 'refunded' => true, 'amount_refunded' => 4500];

        self::notify($client, 'charge.refunded', $refund);

        self::assertResponseStatusCodeSame(503);
        self::assertSame(['pending', 0, []], [self::stored($order)->status, self::stored($order)->refundedCents, self::stored($order)->creditNotes]);

        self::notify($client, 'checkout.session.completed', self::stripePays((string) $order->checkoutSessionId()));
        self::notify($client, 'charge.refunded', $refund);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('refunded', self::stored($order)->status);
        self::assertSame(['2026-0002'], array_column(self::creditNotes(self::stored($order)), 0));
    }

    public function testAnUpdatedRefundIsRecordedLikeARefundedCharge(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);
        $intent = (string) self::stored($order)->paymentIntentId;
        self::mails()->broken = 'credit note';
        $refund = self::stripeRefunds($intent, 4500);
        self::mails()->broken = null;

        self::notify($client, 'charge.refund.updated', ['id' => $refund, 'object' => 'refund', 'payment_intent' => $intent, 'amount' => 4500, 'status' => 'succeeded']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['refunded', [$refund]], [self::stored($order)->status, array_column(self::creditNotes(self::stored($order)), 3)]);
        self::assertCount(1, self::mails()->creditNotes);
    }

    public function testAFailedCreditNoteMailAnswers500AndTheRetryOnlySendsTheMail(): void
    {
        $client = self::shop();
        self::openShop();
        $order = self::paidOrder($client);
        $intent = (string) self::stored($order)->paymentIntentId;
        self::mails()->broken = 'credit note';

        self::stripeRefunds($intent, 4500);
        $refund = ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => $intent, 'refunded' => true, 'amount_refunded' => 4500];
        self::notify($client, 'charge.refunded', $refund);

        self::assertResponseStatusCodeSame(500);
        self::assertSame([], self::mails()->creditNotes);

        self::mails()->broken = null;
        self::notify($client, 'charge.refunded', $refund);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['2026-0002'], array_column(self::creditNotes(self::stored($order)), 0));
        self::assertCount(1, self::mails()->creditNotes);
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

    private static function paidOrder(KernelBrowser $client): Order
    {
        $order = self::pendingOrder();
        self::notify($client, 'checkout.session.completed', self::stripePays((string) $order->checkoutSessionId()));
        self::assertResponseStatusCodeSame(200);

        return $order;
    }

    private static function stripeRefunds(string $paymentIntentId, int $amountCents, ?string $reason = null): string
    {
        $refund = self::getContainer()->get(HttpClientInterface::class)->request('POST', 'https://api.stripe.com/v1/refunds', [
            'body' => ['payment_intent' => $paymentIntentId, 'amount' => $amountCents] + (null === $reason ? [] : ['metadata' => ['reason' => $reason]]),
        ])->toArray();
        self::assertIsString($refund['id']);

        return $refund['id'];
    }

    /** @return list<array{string, int, ?string, string, string}> */
    private static function creditNotes(OrderView $order): array
    {
        return array_map(static fn (CreditNoteView $note): array => [$note->number, $note->amount->cents, $note->reason, $note->stripeRefundId, $note->invoiceNumber], $order->creditNotes);
    }
}
