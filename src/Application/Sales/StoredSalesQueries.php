<?php

declare(strict_types=1);

namespace App\Application\Sales;

use App\Domain\Lessons\LessonFormat;
use App\Domain\Sales\CreditNote;
use App\Domain\Sales\CreditNoteRepository;
use App\Domain\Sales\Customer;
use App\Domain\Sales\FiscalYear;
use App\Domain\Sales\Invoice;
use App\Domain\Sales\InvoiceRepository;
use App\Domain\Sales\Order;
use App\Domain\Sales\OrderRepository;
use App\Domain\Sales\OrderStatus;
use App\Domain\Sales\ProductType;
use App\Domain\Sales\VatCase;
use App\Domain\Sales\WithdrawalRequestRepository;
use Symfony\Component\Uid\Ulid;

final readonly class StoredSalesQueries implements SalesQueries
{
    private const string INVOICE = 'Facture';
    private const string CREDIT_NOTE = 'Avoir';

    public function __construct(
        private OrderRepository $orders,
        private InvoiceRepository $invoices,
        private CreditNoteRepository $creditNotes,
        private WithdrawalRequestRepository $withdrawals,
    ) {
    }

    public function orders(?OrderStatus $status = null, ?int $year = null): array
    {
        return array_map(static fn (Order $order): OrderView => OrderView::of($order), $this->orders->newestFirst($status, $year));
    }

    public function order(string $id): ?OrderView
    {
        $order = Ulid::isValid($id) ? $this->orders->find(Ulid::fromString($id)) : null;

        return null === $order ? null : OrderView::of($order, $this->withdrawals->forOrder($order->id()), $this->creditNotes->forOrder($order->id()));
    }

    public function invoice(string $orderId): ?InvoiceView
    {
        $invoice = Ulid::isValid($orderId) ? $this->invoices->forOrder(Ulid::fromString($orderId)) : null;

        return null === $invoice ? null : InvoiceView::of($invoice);
    }

    public function creditNote(string $id): ?CreditNoteView
    {
        $note = Ulid::isValid($id) ? $this->creditNotes->find(Ulid::fromString($id)) : null;

        return null === $note ? null : CreditNoteView::of($note);
    }

    public function revenueBook(int $year): array
    {
        return array_column($this->entries($year), 1);
    }

    public function totals(int $year): Totals
    {
        $groups = [];
        $digital = 0;
        $onlineLessons = 0;
        foreach ($this->entries($year) as [$order, $line]) {
            $key = $line->country.$line->productType;
            $previous = $groups[$key] ?? new CountryTotal($line->country, $line->productType, 0, 0);
            $groups[$key] = new CountryTotal($line->country, $line->productType, $previous->amountCents + $line->amountCents, $previous->count + (int) (self::INVOICE === $line->label));
            $abroad = self::isEuConsumerAbroad($order);
            $digital += $abroad && ProductType::Beat === $order->productType() ? $line->amountCents : 0;
            $onlineLessons += $abroad && LessonFormat::Online->value === $order->productFormat() ? $line->amountCents : 0;
        }
        ksort($groups);

        return new Totals(array_values($groups), $digital, $onlineLessons);
    }

    /** @return list<array{Order, RevenueLine}> */
    private function entries(int $year): array
    {
        $entries = [
            ...array_map(static fn (Order $order): array => [$order, self::invoiceLine($order)], $this->orders->paidIn($year)),
            ...array_map($this->creditEntry(...), $this->creditNotes->issuedIn($year)),
        ];
        usort($entries, static fn (array $a, array $b): int => $a[1]->date <=> $b[1]->date);

        return $entries;
    }

    /** @return array{Order, RevenueLine} */
    private function creditEntry(CreditNote $note): array
    {
        $order = $this->orders->get($note->orderId());

        return [$order, self::line($order, $note->issuedAt(), $note->number(), $note->buyer(), -$note->amount()->cents, self::CREDIT_NOTE)];
    }

    private static function invoiceLine(Order $order): RevenueLine
    {
        return self::line($order, $order->paidAt() ?? $order->createdAt(), $order->invoiceNumber() ?? '', $order->customer(), $order->price()->cents, self::INVOICE);
    }

    private static function line(Order $order, \DateTimeImmutable $date, string $number, ?Customer $customer, int $amountCents, string $label): RevenueLine
    {
        return new RevenueLine(
            $date->setTimezone(new \DateTimeZone(FiscalYear::TIMEZONE)),
            $number,
            $order->reference(),
            $customer->name ?? '',
            $customer->country ?? '',
            $order->productType()->value,
            $amountCents,
            Invoice::PAYMENT_METHOD,
            $label,
        );
    }

    private static function isEuConsumerAbroad(Order $order): bool
    {
        $customer = $order->customer();

        return null !== $customer && VatCase::isEuConsumerAbroad($customer);
    }
}
