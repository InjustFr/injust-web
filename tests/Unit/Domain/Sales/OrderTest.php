<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Consent;
use App\Domain\Sales\ConsentKind;
use App\Domain\Sales\Customer;
use App\Domain\Sales\Exception\InvalidRefundAmount;
use App\Domain\Sales\Exception\MissingConsent;
use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\Exception\PaymentMismatch;
use App\Domain\Sales\Exception\RefundBeforePayment;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\Purchasable;
use App\Domain\Shared\Exception\UnsupportedLocale;
use App\Domain\Shared\Price;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testAPlacedOrderIsPendingAndSnapshotsTheProduct(): void
    {
        $order = SalesExamples::order(SalesExamples::lesson(4500, 'online'), '2026-11-10 10:00:00', 'en', ' buyer@example.test ');

        self::assertSame(OrderStatus::Pending, $order->status());
        self::assertMatchesRegularExpression('/^INJ-[0-9A-HJKMNP-TV-Z]{6}$/', $order->reference());
        self::assertSame(substr($order->id()->toBase32(), -6), substr($order->reference(), -6));
        self::assertSame([ProductType::Lesson, '01JCLESSON0000000000000000', 'Cours de guitare', 'online', 4500], [$order->productType(), $order->productId(), $order->productLabel(), $order->productFormat(), $order->price()->cents]);
        self::assertSame(['en', 'buyer@example.test', 3], [$order->locale(), $order->email(), $order->cgvVersion()]);
        self::assertEquals(SalesExamples::terms(), $order->terms());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:00:00'), $order->createdAt());
        self::assertNull($order->checkoutSessionId());
        self::assertNull($order->paymentIntentId());
        self::assertNull($order->paidAt());
        self::assertNull($order->customer());
        self::assertNull($order->invoiceNumber());
        self::assertNull($order->confirmationSentAt());
        self::assertFalse($order->needsConfirmation());
    }

    public function testALongLabelIsCutWithoutBreakingACharacter(): void
    {
        $order = SalesExamples::order(new Purchasable(ProductType::Lesson, 'id', str_repeat('é', 300), new Price(100)));

        self::assertSame(str_repeat('é', 255), $order->productLabel());
    }

    public function testTheConsentsAreKeptWithTheirTextAndTime(): void
    {
        $order = SalesExamples::order(SalesExamples::production(), '2026-11-10 10:00:00');

        self::assertEquals(SalesExamples::consents(ProductType::Production), $order->consents());
        self::assertSame([ConsentKind::Terms, ConsentKind::EarlyStart, ConsentKind::ProRata], array_map(static fn (Consent $consent): ConsentKind => $consent->kind, $order->consents()));
    }

    public function testEachRequiredConsentMustBeGiven(): void
    {
        $consents = SalesExamples::consents(ProductType::Lesson);
        array_pop($consents);

        $this->expectExceptionObject(new MissingConsent());

        Order::place(SalesExamples::lesson(), 'fr', 'buyer@example.test', $consents, SalesExamples::terms(), new \DateTimeImmutable());
    }

    public function testAConsentOfAnotherProductDoesNotReplaceARequiredOne(): void
    {
        $consents = [...SalesExamples::consents(ProductType::Beat), ...array_slice(SalesExamples::consents(ProductType::Lesson), 0, 2)];

        $this->expectExceptionObject(new MissingConsent());

        Order::place(SalesExamples::lesson(), 'fr', 'buyer@example.test', $consents, SalesExamples::terms(), new \DateTimeImmutable());
    }

    public function testAnOrderIsPlacedInFrenchOrEnglishOnly(): void
    {
        $this->expectExceptionObject(new UnsupportedLocale('de'));

        SalesExamples::order(locale: 'de');
    }

    public function testThePaymentPaysThePendingOrderWithTheStripeCustomer(): void
    {
        $order = SalesExamples::order();
        $order->attachSession('cs_1');
        $customer = SalesExamples::customer('BE', 'BE0123456789', 'Riot SRL');

        self::assertTrue($order->pay(SalesExamples::payment($order, $customer, '2026-11-10 10:05:00')));

        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertSame(['cs_1', 'pi_'.$order->reference(), 'stripe@example.test'], [$order->checkoutSessionId(), $order->paymentIntentId(), $order->email()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00'), $order->paidAt());
        self::assertEquals($customer, $order->customer());
        self::assertTrue($order->needsConfirmation());
    }

    public function testTheFormEmailStaysWhenStripeGivesNone(): void
    {
        $order = SalesExamples::order();
        $customer = new Customer('Test Buyer', '', 'FR', '1 rue du Son', '', '75000', 'Paris');

        $order->pay(SalesExamples::payment($order, $customer));

        self::assertSame('buyer@example.test', $order->email());
    }

    public function testAReplayedPaymentChangesNothing(): void
    {
        $order = SalesExamples::order();
        $order->pay(SalesExamples::payment($order, paidAt: '2026-11-10 10:05:00'));

        self::assertFalse($order->pay(SalesExamples::payment($order, SalesExamples::customer('DE'), '2026-11-11 10:00:00', 1)));

        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00'), $order->paidAt());
        self::assertSame('FR', $order->customer()?->country);
    }

    public function testALateAsyncPaymentPaysAnExpiredOrder(): void
    {
        $order = SalesExamples::order();
        $order->expire();

        self::assertTrue($order->pay(SalesExamples::payment($order)));
        self::assertSame(OrderStatus::Paid, $order->status());
    }

    public function testARefundedOrderIsNotPaidAgain(): void
    {
        $order = SalesExamples::order();
        $order->pay(SalesExamples::payment($order));
        $order->refund($order->price()->cents);

        self::assertFalse($order->pay(SalesExamples::payment($order)));
        self::assertSame(OrderStatus::Refunded, $order->status());
    }

    public function testAnAmountOtherThanThePriceIsRefused(): void
    {
        $order = SalesExamples::order(SalesExamples::lesson(4500));

        try {
            $order->pay(SalesExamples::payment($order, cents: 4499));
            self::fail('A wrong amount was accepted.');
        } catch (PaymentMismatch $mismatch) {
            self::assertSame(['reference' => $order->reference()], $mismatch->parameters());
        }

        self::assertSame(OrderStatus::Pending, $order->status());
        self::assertNull($order->paidAt());
    }

    public function testACurrencyOtherThanTheEuroIsRefused(): void
    {
        $order = SalesExamples::order();

        $this->expectException(PaymentMismatch::class);

        $order->pay(SalesExamples::payment($order, currency: 'usd'));
    }

    public function testTheEuroIsRecognisedWhateverItsCase(): void
    {
        $order = SalesExamples::order();

        self::assertTrue($order->pay(SalesExamples::payment($order, currency: 'EUR')));
    }

    public function testOnlyAPendingOrderExpires(): void
    {
        $pending = SalesExamples::order();
        $paid = SalesExamples::paid();

        $pending->expire();
        $paid->expire();

        self::assertSame([OrderStatus::Expired, OrderStatus::Paid], [$pending->status(), $paid->status()]);
    }

    public function testAPartialRefundKeepsTheOrderPaidAndAFullOneMarksItRefunded(): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(4500));

        $order->refund(1500);
        self::assertSame([OrderStatus::Paid, 1500], [$order->status(), $order->refundedCents()]);

        $order->refund(3000);
        self::assertSame([OrderStatus::Refunded, 4500], [$order->status(), $order->refundedCents()]);
        self::assertFalse($order->needsConfirmation());
    }

    public function testAWithdrawnOrderStaysWithdrawnUntilFullyRefunded(): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(4500), paidAt: '2026-11-10 10:05:00 UTC');
        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        $order->refund(2000);
        self::assertSame(OrderStatus::Withdrawn, $order->status());

        $order->refund(2500);
        self::assertSame(OrderStatus::Refunded, $order->status());
    }

    public function testANewOrderHasNothingRefunded(): void
    {
        $order = SalesExamples::order(SalesExamples::lesson(4500));

        self::assertSame([0, null], [$order->refundedCents(), $order->withdrawnAt()]);
    }

    /** @return iterable<string, array{\Closure(Order): void}> */
    public static function unpaid(): iterable
    {
        yield 'pending' => [static function (Order $order): void {
        }];
        yield 'expired' => [static function (Order $order): void {
            $order->expire();
        }];
    }

    /** @param \Closure(Order): void $prepare */
    #[DataProvider('unpaid')]
    public function testARefundBeforeThePaymentIsRefused(\Closure $prepare): void
    {
        $order = SalesExamples::order();
        $prepare($order);

        try {
            $order->refund(100);
            self::fail('A refund before the payment was accepted.');
        } catch (RefundBeforePayment $refused) {
            self::assertSame(['sales.refund_before_payment', ['reference' => $order->reference()]], [$refused->getMessage(), $refused->parameters()]);
        }

        self::assertSame(0, $order->refundedCents());
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidAmounts(): iterable
    {
        yield 'above the remaining amount' => [3001, 3000];
        yield 'zero' => [0, 3000];
        yield 'negative' => [-1, 3000];
    }

    #[DataProvider('invalidAmounts')]
    public function testARefundMustBeAPositiveAmountWithinWhatRemains(int $cents, int $remaining): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(4500));
        $order->refund(1500);

        try {
            $order->refund($cents);
            self::fail('The refund was accepted.');
        } catch (InvalidRefundAmount $refused) {
            self::assertSame(['sales.invalid_refund_amount', ['remaining' => $remaining / 100]], [$refused->getMessage(), $refused->parameters()]);
        }

        self::assertSame([1500, OrderStatus::Paid], [$order->refundedCents(), $order->status()]);
    }

    public function testNothingMoreIsRefundedOnceFullyRefunded(): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(4500));
        $order->refund(4500);

        $this->expectExceptionObject(new InvalidRefundAmount(0));

        $order->checkRefund(1);
    }

    public function testTheRefundCheckAloneChangesNothing(): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(4500));

        $order->checkRefund(4500);

        self::assertSame([0, OrderStatus::Paid], [$order->refundedCents(), $order->status()]);
    }

    public function testTheConfirmationIsSentOnce(): void
    {
        $order = SalesExamples::paid();

        $order->confirmationSent(new \DateTimeImmutable('2026-11-10 10:06:00'));

        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $order->confirmationSentAt());
        self::assertFalse($order->needsConfirmation());
    }

    public function testAnUnpaidOrderGetsNoConfirmation(): void
    {
        $order = SalesExamples::order();

        $this->expectExceptionObject(new OrderNotPaid($order->reference()));

        $order->confirmationSent(new \DateTimeImmutable());
    }

    public function testTheInvoiceNumberIsKept(): void
    {
        $order = SalesExamples::paid();

        $order->invoiced('2026-0007');

        self::assertSame('2026-0007', $order->invoiceNumber());
    }
}
