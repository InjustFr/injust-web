<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Customer;
use App\Domain\Sales\Exception\WithdrawalNotAllowed;
use App\Domain\Sales\Exception\WithdrawalPeriodOver;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\WithdrawalRequest;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WithdrawalTest extends TestCase
{
    private const string PAID_BEFORE_DST = '2026-03-27 23:30:00 Europe/Paris';

    public function testAPaidLessonIsWithdrawnAndRemembersWhen(): void
    {
        $order = SalesExamples::paid(SalesExamples::lesson(), paidAt: '2026-11-10 10:05:00 UTC');

        $order->withdraw(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'));

        self::assertSame(OrderStatus::Withdrawn, $order->status());
        self::assertEquals(new \DateTimeImmutable('2026-11-12 09:00:00 UTC'), $order->withdrawnAt());
        self::assertFalse($order->needsConfirmation());
    }

    public function testAPaidProductionServiceIsWithdrawn(): void
    {
        $order = SalesExamples::paid(SalesExamples::production(), paidAt: '2026-11-10 10:05:00 UTC');

        $order->withdraw(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));

        self::assertSame(OrderStatus::Withdrawn, $order->status());
    }

    public function testTheLastDayIsTheFourteenthDayAfterThePaymentDayInParis(): void
    {
        $order = SalesExamples::paid(paidAt: self::PAID_BEFORE_DST);

        self::assertSame('2026-04-10T00:00:00+00:00', $order->withdrawalLastDay()?->format(\DATE_ATOM));
        self::assertNull(SalesExamples::order()->withdrawalLastDay());
        self::assertNull(SalesExamples::paid(SalesExamples::beat())->withdrawalLastDay());
    }

    /** @return iterable<string, array{string}> */
    public static function insideThePeriod(): iterable
    {
        yield 'right after the payment' => ['2026-03-27 23:31:00 Europe/Paris'];
        yield 'across the change to summer time' => ['2026-03-29 03:00:00 Europe/Paris'];
        yield 'fourteen times twenty-four hours later' => ['2026-04-10 23:30:00 Europe/Paris'];
        yield 'last second of the fourteenth day' => ['2026-04-10 23:59:59 Europe/Paris'];
        yield 'last second of the fourteenth day in UTC' => ['2026-04-10 21:59:59 UTC'];
    }

    #[DataProvider('insideThePeriod')]
    public function testTheWithdrawalIsAcceptedUntilTheEndOfTheFourteenthDay(string $now): void
    {
        $order = SalesExamples::paid(paidAt: self::PAID_BEFORE_DST);

        $order->withdraw(new \DateTimeImmutable($now));

        self::assertSame(OrderStatus::Withdrawn, $order->status());
    }

    /** @return iterable<string, array{string}> */
    public static function afterThePeriod(): iterable
    {
        yield 'midnight after the fourteenth day' => ['2026-04-11 00:00:00 Europe/Paris'];
        yield 'midnight in UTC' => ['2026-04-10 22:00:00 UTC'];
        yield 'a month later' => ['2026-05-01 10:00:00 Europe/Paris'];
    }

    #[DataProvider('afterThePeriod')]
    public function testTheWithdrawalIsRefusedOnceThePeriodIsOver(string $now): void
    {
        $order = SalesExamples::paid(paidAt: self::PAID_BEFORE_DST);

        try {
            $order->withdraw(new \DateTimeImmutable($now));
            self::fail('A late withdrawal was accepted.');
        } catch (WithdrawalPeriodOver $over) {
            self::assertSame('sales.withdrawal_period_over', $over->getMessage());
            self::assertEquals(['endDate' => new \DateTimeImmutable('2026-04-10 00:00:00 UTC')], $over->parameters());
        }

        self::assertSame(OrderStatus::Paid, $order->status());
        self::assertNull($order->withdrawnAt());
    }

    /** @return iterable<string, array{string, string}> */
    public static function extendedPeriods(): iterable
    {
        yield 'ends on a Saturday' => ['2026-11-14 10:00:00 Europe/Paris', '2026-11-30'];
        yield 'ends on a Sunday' => ['2026-11-15 10:00:00 Europe/Paris', '2026-11-30'];
        yield 'ends on Labour Day, a Friday' => ['2026-04-17 10:00:00 Europe/Paris', '2026-05-04'];
        yield 'ends on Victory Day, a Friday' => ['2026-04-24 10:00:00 Europe/Paris', '2026-05-11'];
        yield 'ends on Ascension' => ['2026-04-30 10:00:00 Europe/Paris', '2026-05-15'];
    }

    #[DataProvider('extendedPeriods')]
    public function testAPeriodEndingOnAWeekendOrPublicHolidayRunsToTheNextWorkingDay(string $paidAt, string $lastDay): void
    {
        $order = SalesExamples::paid(paidAt: $paidAt);

        self::assertSame($lastDay.'T00:00:00+00:00', $order->withdrawalLastDay()?->format(\DATE_ATOM));
        $order->checkWithdrawal(new \DateTimeImmutable($lastDay.' 23:59:59 Europe/Paris'));
        $this->expectExceptionObject(new WithdrawalPeriodOver(new \DateTimeImmutable($lastDay.' 00:00:00 UTC')));
        $order->checkWithdrawal(new \DateTimeImmutable($lastDay.' 00:00:00 Europe/Paris')->modify('+1 day'));
    }

    public function testABeatCannotBeWithdrawnBecauseTheBuyerWaivedTheRight(): void
    {
        $order = SalesExamples::paid(SalesExamples::beat(), paidAt: '2026-11-10 10:05:00 UTC');

        $this->expectExceptionObject(new WithdrawalNotAllowed($order->reference()));

        $order->withdraw(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));
    }

    /** @return iterable<string, array{\Closure(Order): void}> */
    public static function notPaid(): iterable
    {
        yield 'pending' => [static function (Order $order): void {
        }];
        yield 'expired' => [static function (Order $order): void {
            $order->expire();
        }];
    }

    /** @param \Closure(Order): void $prepare */
    #[DataProvider('notPaid')]
    public function testAnUnpaidOrderCannotBeWithdrawn(\Closure $prepare): void
    {
        $order = SalesExamples::order();
        $prepare($order);

        $this->expectExceptionObject(new WithdrawalNotAllowed($order->reference()));

        $order->withdraw(new \DateTimeImmutable('2026-11-10 10:06:00 UTC'));
    }

    public function testAnOrderIsWithdrawnOnce(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC');
        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        try {
            $order->withdraw(new \DateTimeImmutable('2026-11-12 10:00:00 UTC'));
            self::fail('A second withdrawal was accepted.');
        } catch (WithdrawalNotAllowed $refused) {
            self::assertSame(['sales.withdrawal_not_allowed', ['reference' => $order->reference()]], [$refused->getMessage(), $refused->parameters()]);
        }

        self::assertEquals(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'), $order->withdrawnAt());
    }

    public function testAFullyRefundedOrderCannotBeWithdrawn(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC');
        $order->refund($order->price()->cents);

        $this->expectException(WithdrawalNotAllowed::class);

        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));
    }

    public function testAPartlyRefundedOrderCanStillBeWithdrawn(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC');
        $order->refund(100);

        $order->withdraw(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        self::assertSame(OrderStatus::Withdrawn, $order->status());
    }

    public function testTheCheckAloneChangesNothing(): void
    {
        $order = SalesExamples::paid(paidAt: '2026-11-10 10:05:00 UTC');

        $order->checkWithdrawal(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        self::assertSame(OrderStatus::Paid, $order->status());
        $this->expectException(WithdrawalPeriodOver::class);
        $order->checkWithdrawal(new \DateTimeImmutable('2026-11-25 00:00:00 Europe/Paris'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function emails(): iterable
    {
        yield 'same address' => ['stripe@example.test', true];
        yield 'other case and spaces' => ['  Stripe@EXAMPLE.test ', true];
        yield 'upper case' => ['STRIPE@EXAMPLE.TEST', true];
        yield 'another address' => ['other@example.test', false];
        yield 'blank' => ['', false];
        yield 'blank with spaces' => ['   ', false];
    }

    #[DataProvider('emails')]
    public function testTheOrderBelongsToItsEmailWhateverTheCase(string $email, bool $matches): void
    {
        self::assertSame($matches, SalesExamples::paid()->belongsTo($email));
    }

    public function testTheFormEmailMatchesWhenStripeGaveNone(): void
    {
        $order = SalesExamples::order(email: 'Form@Example.test');
        $order->pay(SalesExamples::payment($order, new Customer('Test Buyer', '', 'FR', '1 rue du Son', '', '75000', 'Paris')));

        self::assertTrue($order->belongsTo('form@example.test'));
        self::assertFalse($order->belongsTo('stripe@example.test'));
    }

    public function testAccentedLettersMatchWhateverTheirCase(): void
    {
        self::assertTrue(SalesExamples::order(email: 'élodie@example.test')->belongsTo('ÉLODIE@example.test'));
        self::assertTrue(SalesExamples::order(email: 'ÉLODIE@example.test')->belongsTo('élodie@example.test'));
    }

    public function testAnOrderWithoutEmailBelongsToNobody(): void
    {
        self::assertFalse(SalesExamples::order(email: '')->belongsTo(''));
    }

    public function testTheRequestKeepsWhatTheCustomerSent(): void
    {
        $order = SalesExamples::paid();
        $request = WithdrawalRequest::receive($order, ' Buyer@Example.test ', ' Test Buyer ', ' I changed my mind. ', 'en', new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        self::assertSame([$order->id()->toBase32(), $order->reference(), 'Buyer@Example.test', 'Test Buyer', 'I changed my mind.', 'en'], [$request->orderId()->toBase32(), $request->orderReference(), $request->email(), $request->name(), $request->reason(), $request->locale()]);
        self::assertEquals(new \DateTimeImmutable('2026-11-11 10:00:00 UTC'), $request->receivedAt());
        self::assertNull($request->acknowledgedAt());
        self::assertNotSame($order->id()->toBase32(), $request->id()->toBase32());
    }

    public function testBlankOptionalFieldsAreLeftOut(): void
    {
        $request = WithdrawalRequest::receive(SalesExamples::paid(), 'buyer@example.test', '  ', '', 'fr', new \DateTimeImmutable());

        self::assertSame([null, null], [$request->name(), $request->reason()]);
        self::assertNull(WithdrawalRequest::receive(SalesExamples::paid(), 'b@x.test', null, null, 'fr', new \DateTimeImmutable())->name());
    }

    public function testLongFieldsAreCutWithoutBreakingACharacter(): void
    {
        $request = WithdrawalRequest::receive(SalesExamples::paid(), 'buyer@example.test', str_repeat('é', 150), str_repeat('è', 2500), 'fr', new \DateTimeImmutable());

        self::assertSame([str_repeat('é', 100), str_repeat('è', 2000)], [$request->name(), $request->reason()]);
    }

    public function testTheAcknowledgementTimeIsKeptAndUpdatedOnResend(): void
    {
        $request = WithdrawalRequest::receive(SalesExamples::paid(), 'buyer@example.test', null, null, 'fr', new \DateTimeImmutable('2026-11-11 10:00:00 UTC'));

        $request->acknowledged(new \DateTimeImmutable('2026-11-11 10:00:05 UTC'));
        self::assertEquals(new \DateTimeImmutable('2026-11-11 10:00:05 UTC'), $request->acknowledgedAt());

        $request->acknowledged(new \DateTimeImmutable('2026-11-12 08:00:00 UTC'));
        self::assertEquals(new \DateTimeImmutable('2026-11-12 08:00:00 UTC'), $request->acknowledgedAt());
    }
}
