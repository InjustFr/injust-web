<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Lessons\LessonFormat;
use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\VatCase;
use Symfony\Component\Uid\Ulid;

final readonly class StoredSalesQueries implements SalesQueries
{
    public function __construct(
        private OrderRepository $orders,
        private InvoiceRepository $invoices,
    ) {
    }

    public function orders(?OrderStatus $status = null, ?int $year = null): array
    {
        return array_map(OrderView::of(...), $this->orders->newestFirst($status, $year));
    }

    public function order(string $id): ?OrderView
    {
        $order = Ulid::isValid($id) ? $this->orders->find(Ulid::fromString($id)) : null;

        return null === $order ? null : OrderView::of($order);
    }

    public function invoice(string $orderId): ?InvoiceView
    {
        $invoice = Ulid::isValid($orderId) ? $this->invoices->forOrder(Ulid::fromString($orderId)) : null;

        return null === $invoice ? null : InvoiceView::of($invoice);
    }

    public function revenueBook(int $year): array
    {
        return array_map(self::line(...), $this->orders->paidIn($year));
    }

    public function totals(int $year): Totals
    {
        $groups = [];
        $digital = 0;
        $onlineLessons = 0;
        foreach ($this->orders->paidIn($year) as $order) {
            $line = self::line($order);
            $key = $line->country.$line->productType;
            $previous = $groups[$key] ?? new CountryTotal($line->country, $line->productType, 0, 0);
            $groups[$key] = new CountryTotal($line->country, $line->productType, $previous->amountCents + $line->amountCents, $previous->count + 1);
            $abroad = self::isEuConsumerAbroad($order);
            $digital += $abroad && ProductType::Beat === $order->productType() ? $line->amountCents : 0;
            $onlineLessons += $abroad && LessonFormat::Online->value === $order->productFormat() ? $line->amountCents : 0;
        }
        ksort($groups);

        return new Totals(array_values($groups), $digital, $onlineLessons);
    }

    private static function line(Order $order): RevenueLine
    {
        $customer = $order->customer();

        return new RevenueLine(
            ($order->paidAt() ?? $order->createdAt())->setTimezone(new \DateTimeZone(FiscalYear::TIMEZONE)),
            $order->invoiceNumber() ?? '',
            $order->reference(),
            $customer->name ?? '',
            $customer->country ?? '',
            $order->productType()->value,
            $order->price()->cents,
            Invoice::PAYMENT_METHOD,
        );
    }

    private static function isEuConsumerAbroad(Order $order): bool
    {
        $customer = $order->customer();

        return null !== $customer && VatCase::isEuConsumerAbroad($customer);
    }
}
