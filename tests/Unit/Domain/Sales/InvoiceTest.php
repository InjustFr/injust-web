<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Sales;

use App\Domain\Sales\Exception\OrderNotPaid;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceLine;
use App\Domain\Sales\Seller;
use App\Domain\Sales\VatCase;
use App\Domain\Settings\LegalIdentity;
use App\Domain\Settings\VatMentions;
use App\Domain\Shared\Price;
use App\Tests\Support\Sales\SalesExamples;
use PHPUnit\Framework\TestCase;

final class InvoiceTest extends TestCase
{
    public function testAnInvoiceSnapshotsTheSellerTheBuyerAndThePaidOrder(): void
    {
        $customer = SalesExamples::customer('DE', 'DE123456789', 'Riot GmbH');
        $order = SalesExamples::paid(SalesExamples::lesson(4500), $customer, '2026-11-10 10:05:00');

        $invoice = Invoice::issue('2026-0001', $order, self::seller(), new VatMentions('Franchise', 'Autoliquidation', 'Hors UE'), new \DateTimeImmutable('2026-11-10 10:06:00'));

        self::assertSame(['2026-0001', $order->reference(), 'fr', 'card', null], [$invoice->number(), $invoice->orderReference(), $invoice->locale(), $invoice->paymentMethod(), $invoice->creditedInvoiceNumber()]);
        self::assertTrue($order->id()->equals($invoice->orderId()));
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:06:00'), $invoice->issuedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-10 10:05:00'), $invoice->paidAt());
        self::assertEquals(Seller::of(self::identity()), $invoice->seller());
        self::assertEquals($customer, $invoice->buyer());
        self::assertEquals([new InvoiceLine('Cours de guitare', 1, new Price(4500), new Price(4500))], $invoice->lines());
        self::assertSame(4500, $invoice->total()->cents);
        self::assertSame([VatCase::EuBusiness, 'Autoliquidation'], [$invoice->vatCase(), $invoice->vatMention()]);
    }

    public function testTheSellerKeepsTheIdentityAnInvoiceNeeds(): void
    {
        $seller = Seller::of(self::identity());

        self::assertSame(['Louis Injust', 'EI', '443061841', '1 rue du Son, 75000 Paris', 'louis@injust.test', '+33 6 00 00 00 00'], [$seller->name, $seller->businessForm, $seller->siren, $seller->address, $seller->email, $seller->phone]);
        self::assertEquals($seller, Seller::fromArray($seller->toArray()));
    }

    public function testAnUnpaidOrderIsNotInvoiced(): void
    {
        $order = SalesExamples::order();

        $this->expectExceptionObject(new OrderNotPaid($order->reference()));

        Invoice::issue('2026-0001', $order, self::seller(), VatMentions::defaults(), new \DateTimeImmutable());
    }

    private static function seller(): Seller
    {
        return Seller::of(self::identity());
    }

    private static function identity(): LegalIdentity
    {
        return new LegalIdentity('Louis Injust', 'EI', '443 061 841', '1 rue du Son, 75000 Paris', 'louis@injust.test', '+33 6 00 00 00 00', 'Louis Injust', 'Médiateur Test', 'https://mediator.test', 'OVH SAS', '2 rue Kellermann', '+33 9 72 10 10 07');
    }
}
